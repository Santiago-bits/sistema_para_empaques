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
        // Multigalpón: sólo la gente del galpón activo (el Super Administrador no recibe los avisos operativos de cada galpón).
        $warehouseId = \App\Support\Tenancy::ready() ? \App\Support\CurrentWarehouse::id() : null;

        return User::query()
            ->when(\App\Support\Tenancy::ready(), fn ($q) => $q->where('warehouse_id', $warehouseId ?? 0))
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
