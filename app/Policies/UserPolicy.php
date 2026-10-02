<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    /** Un administrador común no puede modificar a un Super Administrador. */
    public function update(User $actor, User $user): bool
    {
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return $actor->can('users.manage');
    }

    /** Nadie (salvo el super admin) maneja sus propios permisos individuales: evita autoasignarse accesos. */
    public function managePermissions(User $actor, User $user): bool
    {
        if ($actor->is($user) && ! $actor->isSuperAdmin()) {
            return false;
        }

        return $this->update($actor, $user) && $actor->can('roles.manage');
    }
}
