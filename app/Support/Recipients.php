<?php

namespace App\Support;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinatarios de notificaciones internas: usuarios activos que tienen un permiso
 * (respeta módulos desactivados y el Super Administrador vía Gate::before).
 */
class Recipients
{
    /** @return Collection<int, User> */
    public static function withPermission(string $permission): Collection
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->with('role')
            ->get()
            ->filter(fn (User $user) => $user->can($permission))
            ->values();
    }

    /** @return Collection<int, User> */
    public static function developers(): Collection
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->with('role')
            ->get()
            ->filter(fn (User $user) => $user->isSuperAdmin())
            ->values();
    }
}
