<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly AuditService $audit, private readonly SessionService $sessions)
    {
    }

    public function create(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = User::query()->create(Arr::except($data, ['warehouses', 'password_confirmation', 'access_mode', 'sectors']));
            $user->warehouses()->sync($data['warehouses'] ?? []);
            if (($data['access_mode'] ?? 'role') === 'sectors') {
                $this->syncSectors($user, $data['sectors'] ?? [], $actor ?? auth()->user());
            }

            return $user;
        });
    }

    /**
     * Acceso por sectores: el usuario queda con el rol base «Empleado» y los permisos de cada sector
     * tildado como permisos individuales. Sólo se reparten sectores que quien edita tiene completos.
     *
     * @param  list<string>  $sectors
     */
    public function syncSectors(User $user, array $sectors, ?User $actor): void
    {
        $allowed = $actor ? \App\Support\Sectors::assignableBy($actor) : array_keys(\App\Support\Sectors::all());
        $chosen = array_values(array_intersect($sectors, $allowed));
        if ($chosen === []) {
            throw new BusinessException('Elegí al menos un sector que puedas asignar.');
        }

        $user->load('role.permissions');
        $fromRole = $user->role?->permissions->pluck('slug')->all() ?? [];
        $grants = array_values(array_diff(\App\Support\Sectors::permissionsFor($chosen), $fromRole));
        $ids = Permission::query()->whereIn('slug', $grants)->pluck('id');

        $before = $user->permissionOverrides()->pluck('slug')->all();
        $user->permissionOverrides()->sync($ids->mapWithKeys(fn ($id) => [$id => ['granted' => true]])->all());
        $user->flushPermissionCache();

        $labels = array_map(fn ($k) => \App\Support\Sectors::all()[$k]['label'], $chosen);
        $this->audit->log('permissions', $user, ['overrides' => $before], ['sectors' => $chosen], 'Sectores asignados: '.implode(', ', $labels));
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($user->is($actor) && ($data['status'] ?? null) !== UserStatus::Active->value) {
            throw new BusinessException('No podés desactivar tu propio usuario.');
        }
        $this->guardLastSuperAdmin($user, $data);
        $mode = $data['access_mode'] ?? 'role';
        $wasSectors = $user->role?->slug === \App\Support\Sectors::ROLE;
        // Nadie (salvo el super admin) cambia sus propios accesos: evita autoasignarse más permisos.
        if ($user->is($actor) && ! $actor->isSuperAdmin()) {
            $sameRole = (int) ($data['role_id'] ?? $user->role_id) === (int) $user->role_id;
            $sameSectors = $mode !== 'sectors' || array_values(array_diff($data['sectors'] ?? [], \App\Support\Sectors::of($user))) === [];
            if (! $sameRole || ! $sameSectors) {
                throw new BusinessException('No podés cambiar tus propios accesos. Pedíselo a otro administrador.');
            }
        }

        return DB::transaction(function () use ($user, $data, $mode, $wasSectors, $actor) {
            $sectors = $data['sectors'] ?? [];
            $data = Arr::except($data, ['access_mode', 'sectors']);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $wasActive = $user->isActive();
            $status = UserStatus::from($data['status']);
            $data['deactivated_at'] = $status === UserStatus::Active ? null : ($user->deactivated_at ?? now());

            $user->update(Arr::except($data, ['warehouses', 'password_confirmation']));
            $user->warehouses()->sync($data['warehouses'] ?? []);

            // Al dar de baja a un usuario, o al cambiarle la contraseña, se cierran sus sesiones y tokens de API.
            $passwordChanged = ! empty($data['password']);
            if (($wasActive && $status !== UserStatus::Active) || ($passwordChanged && ! $user->is(auth()->user()))) {
                $this->sessions->terminateAllFor($user);
            }
            if ($passwordChanged) {
                $user->forceFill(['password_changed_at' => now()])->saveQuietly();
            }

            if ($mode === 'sectors' && ! ($user->is($actor) && ! $actor->isSuperAdmin())) {
                $this->syncSectors($user->fresh(), $sectors, $actor);
            } elseif ($mode !== 'sectors' && $wasSectors) {
                // Pasó de «por sectores» a un rol: los permisos de los sectores dejan de valer.
                $user->permissionOverrides()->sync([]);
                $user->flushPermissionCache();
            }

            return $user;
        });
    }

    /**
     * Administración general: corrige los datos con los que alguien ingresa o se lo contacta (usuario, email,
     * teléfono), por ejemplo cuando se olvidó con qué email se registró. Queda auditado con el motivo.
     *
     * @param  array{username: string, email: ?string, phone: ?string}  $data
     */
    public function updateAccessData(User $user, array $data, User $actor, string $reason): void
    {
        $fields = ['username', 'email', 'phone'];
        $old = $user->only($fields);
        $new = Arr::only($data, $fields);
        if ($old == $new) {
            return;
        }
        $user->update($new);
        $this->audit->log('access_data', $user, $old, $new, 'Corrigió los datos de acceso de '.$user->username, $reason);
    }

    /** Administración general: activa o da de baja a un usuario (al darlo de baja se cierran sus sesiones). */
    public function setStatus(User $user, UserStatus $status, User $actor, string $reason): void
    {
        if ($user->is($actor)) {
            throw new BusinessException('No podés cambiar el estado de tu propio usuario.');
        }
        $this->guardLastSuperAdmin($user, ['status' => $status->value]);

        DB::transaction(function () use ($user, $status, $reason) {
            $old = $user->status;
            $user->update([
                'status' => $status,
                'deactivated_at' => $status === UserStatus::Active ? null : ($user->deactivated_at ?? now()),
            ]);
            if ($status !== UserStatus::Active) {
                $this->sessions->terminateAllFor($user);
            }
            $this->audit->log('status_changed', $user, ['status' => $old?->value], ['status' => $status->value],
                $user->username.': '.$status->label(), $reason);
        });
    }

    /** Cierra todas las sesiones abiertas y tokens de un usuario (por ejemplo, si perdió el celular). */
    public function terminateSessions(User $user, User $actor): void
    {
        $this->sessions->terminateAllFor($user);
        $this->audit->log('sessions_closed', $user, description: 'Cerró todas las sesiones de '.$user->username);
    }

    /**
     * Guarda las excepciones individuales de permisos respecto del rol.
     *
     * @param  array<string, string>  $overrides  slug => 'grant' | 'revoke' | 'inherit'
     */
    public function syncPermissionOverrides(User $user, array $overrides): void
    {
        DB::transaction(function () use ($user, $overrides) {
            $before = $user->permissionOverrides()->get()->mapWithKeys(fn ($p) => [$p->slug => $p->pivot->granted ? 'grant' : 'revoke'])->all();

            // Los permisos reservados al super administrador sólo los cambia un super administrador.
            $actor = auth()->user();
            if ($actor && ! $actor->isSuperAdmin()) {
                $overrides = array_diff_key($overrides, array_flip(Permission::SUPER_ADMIN_ONLY))
                    + array_intersect_key($before, array_flip(Permission::SUPER_ADMIN_ONLY));
            }

            $ids = Permission::query()->whereIn('slug', array_keys($overrides))->pluck('id', 'slug');
            $sync = [];
            foreach ($overrides as $slug => $mode) {
                if (isset($ids[$slug]) && in_array($mode, ['grant', 'revoke'], true)) {
                    $sync[$ids[$slug]] = ['granted' => $mode === 'grant'];
                }
            }
            $user->permissionOverrides()->sync($sync);
            $user->flushPermissionCache();

            $after = array_filter($overrides, fn ($m) => $m !== 'inherit');
            $this->audit->log('permissions', $user, $before, $after, 'Permisos individuales actualizados');
        });
    }

    private function guardLastSuperAdmin(User $user, array $data): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }
        $staysSuper = Role::query()->whereKey($data['role_id'] ?? $user->role_id)->value('slug') === Role::SUPER_ADMIN
            && ($data['status'] ?? 'active') === UserStatus::Active->value;
        if ($staysSuper) {
            return;
        }
        $others = User::query()->whereKeyNot($user->id)->where('status', UserStatus::Active->value)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->exists();
        if (! $others) {
            throw new BusinessException('Debe existir al menos un Super Administrador activo.');
        }
    }
}
