<?php

namespace App\Support;

use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Support\Facades\Route;

/**
 * Construye el menú lateral filtrando por: ruta existente, módulo activo y permiso.
 * La pantalla se adapta al usuario: un operador ve pocas opciones, un administrador todas.
 */
class Menu
{
    public static function for(User $user): array
    {
        $modules = app(ModuleService::class);
        $sections = [];

        foreach (config('menu', []) as $section) {
            if (($section['central'] ?? false) && ! config('galpon.central.mode')) {
                continue;
            }
            $items = [];
            foreach ($section['items'] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }
                if (! $modules->enabled($item['module'])) {
                    continue;
                }
                // permission null = cualquier usuario con sesión (p. ej. «Ayuda y atajos»).
                $permissions = (array) $item['permission'];
                if ($permissions !== [] && ! collect($permissions)->contains(fn ($p) => $user->can($p))) {
                    continue;
                }
                // 'match' fija qué pantallas lo marcan como abierto (p. ej. «Todas las fichas» sólo en su índice).
                $pattern = $item['match'] ?? (str_ends_with($item['route'], '.index')
                    ? substr($item['route'], 0, -6).'.*'
                    : $item['route'].'*');
                $item['active'] = request()->routeIs($pattern) && ! request()->routeIs('production.scan*', 'locations.map*') || request()->routeIs($item['route']);
                $items[] = $item;
            }
            if ($items !== []) {
                $sections[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return $sections;
    }
}
