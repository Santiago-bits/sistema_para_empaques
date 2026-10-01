<?php

namespace App\Support;

/**
 * Índice de permisos declarados en config/permissions.php.
 */
class PermissionRegistry
{
    private static ?array $moduleBySlug = null;

    /** @return array<string, string> slug => módulo */
    public static function map(): array
    {
        if (self::$moduleBySlug === null) {
            self::$moduleBySlug = [];
            foreach (config('permissions.permissions', []) as $module => $permissions) {
                foreach (array_keys($permissions) as $slug) {
                    self::$moduleBySlug[$slug] = $module;
                }
            }
        }

        return self::$moduleBySlug;
    }

    public static function moduleOf(string $slug): ?string
    {
        return self::map()[$slug] ?? null;
    }

    public static function exists(string $slug): bool
    {
        return isset(self::map()[$slug]);
    }

    /**
     * Expande la definición de un rol: '*' = todos, '!slug' = excepto.
     *
     * @return list<string>
     */
    public static function expand(array $definition): array
    {
        $all = array_keys(self::map());
        $result = in_array('*', $definition, true) ? $all : [];
        foreach ($definition as $item) {
            if ($item === '*') {
                continue;
            }
            if (str_starts_with($item, '!')) {
                $result = array_values(array_diff($result, [substr($item, 1)]));
            } elseif (! in_array($item, $result, true)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
