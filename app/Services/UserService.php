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

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create(Arr::except($data, ['warehouses', 'password_confirmation']));
            $user->warehouses()->sync($data['warehouses'] ?? []);

            return $user;
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($user->is($actor) && ($data['status'] ?? null) !== UserStatus::Active->value) {
            throw new BusinessException('No podés desactivar tu propio usuario.');
        }
        $this->guardLastSuperAdmin($user, $data);

        return DB::transaction(function () use ($user, $data) {
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

            return $user;
        });
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
