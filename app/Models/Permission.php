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

    protected $fillable = [
        'slug', 'name', 'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
