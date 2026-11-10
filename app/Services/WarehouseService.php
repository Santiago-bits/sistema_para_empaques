<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CurrentWarehouse;
use Database\Seeders\SystemSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Multigalpón: alta y administración de galpones (sólo el Super Administrador).
 * Cada galpón nace con sus datos base (módulos, numeraciones, motivos, turnos, selecciones, temporada)
 * y su propia configuración; después su administrador general carga a sus empleados y sus fichas.
 */
class WarehouseService
{
    public function __construct(private readonly AuditService $audit, private readonly SettingsService $settings)
    {
    }

    /** @param  array{name: string, code?: ?string, cuit?: ?string, address?: ?string, locality?: ?string}  $data */
    public function create(array $data, User $by): Warehouse
    {
        $warehouse = DB::transaction(function () use ($data) {
            $company = Company::query()->create(['name' => $data['name']]);

            return Warehouse::query()->create([
                'company_id' => $company->id,
                'code' => ($data['code'] ?? null) ?: $this->nextCode(),
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'active' => true,
            ]);
        });

        $warehouse->associations()->sync($data['associations'] ?? []);
        CurrentWarehouse::forgetAssociations();
        \App\Support\Tenancy::refresh(); // ahora puede haber más de un galpón
        // Datos base y configuración propia del galpón nuevo.
        (new SystemSeeder)->seedWarehouse($warehouse->id);
        CurrentWarehouse::runFor($warehouse->id, function () use ($data) {
            $this->settings->set('company.name', $data['name']);
            if (filled($data['cuit'] ?? null)) {
                $this->settings->set('company.cuit', preg_replace('/\D/', '', (string) $data['cuit']));
            }
            if (filled($data['address'] ?? null)) {
                $this->settings->set('company.address', $data['address']);
            }
            if (filled($data['locality'] ?? null)) {
                $this->settings->set('company.locality', $data['locality']);
            }
        });

        CurrentWarehouse::runFor($warehouse->id, fn () => $this->audit->log('create', $warehouse, null,
            ['name' => $warehouse->name, 'code' => $warehouse->code], 'Creó el galpón '.$warehouse->name));

        return $warehouse;
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $old = $warehouse->only(['name', 'code', 'address', 'active']);
        $warehouse->update([
            'name' => $data['name'],
            'code' => ($data['code'] ?? null) ?: $warehouse->code,
            'address' => $data['address'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
        ]);
        $oldAssociations = $warehouse->associations()->pluck('name')->all();
        $warehouse->associations()->sync($data['associations'] ?? []);
        CurrentWarehouse::forgetAssociations();
        $old['associations'] = $oldAssociations;
        CurrentWarehouse::runFor($warehouse->id, fn () => $this->audit->log('update', $warehouse, $old, $warehouse->only(['name', 'code', 'address', 'active']) + ['associations' => $warehouse->associations()->pluck('name')->all()],
            $warehouse->active ? 'Actualizó el galpón '.$warehouse->name : 'Suspendió el galpón '.$warehouse->name));

        return $warehouse;
    }

    /**
     * Administrador general del galpón: lo crea el Super Administrador con una contraseña temporal
     * (al entrar la primera vez se la pide cambiar). Después él carga a sus empleados.
     *
     * @param  array{first_name: string, last_name: string, username: string, email?: ?string, password: string}  $data
     */
    public function createAdmin(Warehouse $warehouse, array $data, User $by): User
    {
        $roleId = Role::query()->withoutGlobalScopes()->where('slug', Role::ADMIN)->whereNull('warehouse_id')->value('id')
            ?? throw new BusinessException('Falta el rol Administrador. Actualizá la base de datos desde la administración general.');

        $user = new User([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role_id' => $roleId,
            'status' => UserStatus::Active->value,
            'password' => Hash::make($data['password']),
            'must_change_password' => true,
        ]);
        $user->warehouse_id = $warehouse->id;
        $user->save();
        $user->warehouses()->sync([$warehouse->id]);

        CurrentWarehouse::runFor($warehouse->id, fn () => $this->audit->log('create', $user, null, ['username' => $user->username],
            'Creó el administrador general '.$user->username.' del galpón '.$warehouse->name));

        return $user;
    }

    /** Código siguiente: GAL-0001, GAL-0002… */
    public function nextCode(): string
    {
        $n = (int) Warehouse::query()->count() + 1;
        do {
            $code = 'GAL-'.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (Warehouse::query()->where('code', $code)->exists());

        return $code;
    }

    /** Contraseña temporal fácil de dictar por teléfono (8 caracteres, sin letras que se confundan). */
    public static function temporaryPassword(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        do {
            $password = '';
            for ($i = 0; $i < 8; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (! preg_match('/\d/', $password) || ! preg_match('/[a-z]/', $password));

        return $password;
    }
}
