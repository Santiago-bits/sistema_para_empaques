<?php

namespace App\Support;

use App\Models\User;

/** Sectores de trabajo (config/sectors.php): agrupan permisos para dar acceso sin tecnicismos. */
class Sectors
{
    /** Rol base de los empleados por sectores (sólo tablero, alertas y soporte). */
    public const ROLE = 'employee';

    /** @return array<string, array{label: string, description: string, icon: string, permissions: list<string>}> */
    public static function all(): array
    {
        return config('sectors', []);
    }

    /** @param  list<string>  $keys @return list<string> */
    public static function permissionsFor(array $keys): array
    {
        $all = self::all();

        return array_values(array_unique(array_merge(...array_map(fn ($k) => $all[$k]['permissions'] ?? [], $keys ?: ['_']))));
    }

    /** Sectores que el usuario tiene completos (todos sus permisos efectivos). @return list<string> */
    public static function of(User $user): array
    {
        $slugs = $user->isSuperAdmin() ? null : $user->permissionSlugs()->all();

        return array_keys(array_filter(self::all(), fn ($sector) => $slugs === null || array_diff($sector['permissions'], $slugs) === []));
    }

    /** Sectores que quien edita puede repartir: sólo los que él mismo tiene completos. @return list<string> */
    public static function assignableBy(User $actor): array
    {
        return self::of($actor);
    }
}
