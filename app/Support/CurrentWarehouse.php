<?php

namespace App\Support;

use App\Models\Warehouse;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * El galpón activo. Multigalpón: un solo sistema con muchos galpones, cada uno con sus propios datos.
 *
 * - Un usuario de un galpón trabaja SIEMPRE en su galpón (no puede elegir otro).
 * - El Super Administrador (sin galpón) elige en cuál entrar; se recuerda en la sesión.
 * - Sin usuario (ingreso, páginas públicas, consola): si hay un solo galpón se usa ése; si hay varios, ninguno
 *   (las tareas programadas recorren los galpones con forEach()).
 *
 * Se memoriza en el contenedor (se reinicia en cada request/test).
 */
class CurrentWarehouse
{
    private const KEY = 'galpon.current_warehouse_id';

    /** Evita volver a resolver mientras se carga el usuario de la sesión (el usuario no tiene filtro de galpón). */
    private static bool $resolving = false;

    public static function id(): ?int
    {
        if (app()->bound(self::KEY)) {
            return app(self::KEY);
        }
        if (self::$resolving) {
            return null;
        }

        self::$resolving = true;
        try {
            $id = self::resolve();
        } finally {
            self::$resolving = false;
        }

        // Sólo se memoriza cuando ya se sabe quién es el usuario (antes de iniciar sesión puede cambiar).
        if ($id !== null && (Auth::hasUser() || app()->runningInConsole())) {
            app()->instance(self::KEY, $id);
        }

        return $id;
    }

    public static function model(): ?Warehouse
    {
        $id = self::id();

        return $id ? Warehouse::query()->find($id) : null;
    }

    /** El Super Administrador entra a un galpón. */
    public static function set(int $id): void
    {
        if (app()->bound('session.store') && request()->hasSession()) {
            session(['warehouse_id' => $id]);
        }
        app()->instance(self::KEY, $id);
    }

    public static function forget(): void
    {
        app()->forgetInstance(self::KEY);
        if (app()->bound('session.store') && request()->hasSession()) {
            session()->forget('warehouse_id');
        }
    }

    /** Ejecuta algo dentro de un galpón y después vuelve al anterior. */
    public static function runFor(int $id, Closure $callback): mixed
    {
        $previous = app()->bound(self::KEY) ? app(self::KEY) : null;
        app()->instance(self::KEY, $id);
        app(\App\Services\SettingsService::class)->flushMemory();
        try {
            return $callback($id);
        } finally {
            $previous !== null ? app()->instance(self::KEY, $previous) : app()->forgetInstance(self::KEY);
            app(\App\Services\SettingsService::class)->flushMemory();
        }
    }

    /** Ejecuta algo en cada galpón activo (tareas programadas, alertas, cierres). */
    public static function forEach(Closure $callback): void
    {
        foreach (Warehouse::query()->where('active', true)->orderBy('id')->pluck('id') as $id) {
            self::runFor((int) $id, $callback);
        }
    }

    private static function resolve(): ?int
    {
        if (! Schema::hasTable('warehouses')) {
            return null;
        }
        $user = Auth::hasUser() ? Auth::user() : (self::sessionUserId() ? Auth::user() : null);
        if ($user) {
            if ($user->warehouse_id) {
                return (int) $user->warehouse_id;
            }
            // Super Administrador: el galpón que eligió (si sigue existiendo) o el primero.
            if (app()->bound('session.store') && request()->hasSession() && ($chosen = (int) session('warehouse_id'))) {
                if (Warehouse::query()->whereKey($chosen)->exists()) {
                    return $chosen;
                }
            }

            return self::first();
        }

        // Sin usuario: sólo si hay un único galpón (instalación de un galpón, consola, tests).
        $ids = Warehouse::query()->orderBy('id')->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private static function first(): ?int
    {
        $id = Warehouse::query()->where('active', true)->orderBy('id')->value('id')
            ?? Warehouse::query()->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    private static function sessionUserId(): bool
    {
        $guard = Auth::guard();

        return method_exists($guard, 'getName') && app()->bound('session.store') && request()->hasSession()
            && session()->has($guard->getName());
    }

    /** Al cambiar el usuario (ingreso, salida, actingAs en tests) se vuelve a resolver el galpón. */
    public static function reset(): void
    {
        app()->forgetInstance(self::KEY);
    }
}
