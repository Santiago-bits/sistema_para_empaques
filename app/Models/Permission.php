<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * Permisos que sólo un Super Administrador puede otorgar (a un rol o a un usuario).
     * Un administrador no puede dárselos a nadie, ni a sí mismo.
     */
    public const SUPER_ADMIN_ONLY = ['backups.restore', 'logs.view'];

    /**
     * Combina lo que pidió el usuario con lo existente: si quien edita no es super admin,
     * los permisos protegidos quedan exactamente como estaban.
     *
     * @param  list<string>  $requested
     * @param  list<string>  $current
     * @return list<string>
     */
    public static function guardProtected(array $requested, array $current, User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return array_values(array_unique($requested));
        }

        return array_values(array_unique(array_merge(
            array_diff($requested, self::SUPER_ADMIN_ONLY),
            array_intersect($current, self::SUPER_ADMIN_ONLY),
        )));
    }

    /**
     * Nadie (salvo el super administrador) da permisos que él mismo no tiene: ni a un rol (tampoco al suyo)
     * ni a otro usuario. Sin esto, alguien con «Roles y permisos» podría subirse de rango solo.
     */
    public static function assertCanGrant(array $requested, array $current, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        $added = array_diff($requested, $current, self::SUPER_ADMIN_ONLY);
        $missing = array_values(array_diff($added, $actor->permissionSlugs()->all()));
        if ($missing !== []) {
            $names = self::query()->whereIn('slug', array_slice($missing, 0, 3))->pluck('name')->all();
            throw new \App\Exceptions\BusinessException('No podés dar permisos que vos no tenés: '.implode(', ', $names)
                .(count($missing) > 3 ? ' y '.(count($missing) - 3).' más' : '').'.');
        }
    }

    protected $fillable = [
        'slug', 'name', 'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
