<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Atajos de teclado de config/shortcuts.php resueltos para un usuario: sólo quedan activos los
 * que llevan a secciones de su menú (mismos permisos y módulos que el menú lateral).
 */
class Shortcuts
{
    /** Rutas que cualquier usuario con sesión puede abrir aunque no figuren en el menú. */
    private const ALWAYS = ['help.shortcuts', 'notifications.index'];

    /** @param  array<int, array{items: array<int, array{route: string}>}>  $menu  resultado de Menu::for() */
    public static function for(array $menu): array
    {
        $allowed = collect($menu)->flatMap(fn ($section) => array_column($section['items'], 'route'))
            ->merge(self::ALWAYS)
            ->filter(fn ($route) => Route::has($route))
            ->flip();

        $url = fn (?string $route) => $route !== null && $allowed->has($route) ? route($route) : null;

        $functions = [];
        foreach (config('shortcuts.functions', []) as $key => $fn) {
            if ($target = $url($fn['route'])) {
                $functions[$key] = ['label' => $fn['label'], 'route' => $fn['route'], 'url' => $target];
            }
        }

        $groups = [];
        $go = [];
        foreach (config('shortcuts.groups', []) as $group) {
            if ($group['functions'] ?? false) {
                $items = collect($functions)->map(fn ($fn, $key) => ['keys' => [$key], 'description' => $fn['label']])->values()->all();
            } else {
                $items = [];
                foreach ($group['items'] as $item) {
                    if (array_key_exists('go', $item)) {
                        if (! $target = $url($item['go'])) {
                            continue;
                        }
                        $go[strtolower($item['keys'][1])] = $target;
                    }
                    $items[] = ['keys' => $item['keys'], 'description' => $item['description']];
                }
            }
            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return [
            'groups' => $groups,
            // Lo que necesita resources/js/lib/shortcuts.js.
            'bindings' => [
                'functions' => array_map(fn ($fn) => $fn['url'], $functions),
                'go' => $go,
            ],
            // Ruta → tecla de función, para mostrarla en el menú lateral.
            'routeKeys' => collect($functions)->mapWithKeys(fn ($fn, $key) => [$fn['route'] => $key])->all(),
        ];
    }
}
