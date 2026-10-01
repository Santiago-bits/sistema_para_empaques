<?php

namespace App\Support;

use App\Models\Warehouse;

/**
 * Resuelve el galpón activo. Hoy existe normalmente un solo galpón, pero todo
 * registro operativo guarda warehouse_id para soportar multigalpón sin reescribir.
 * El valor se memoriza en el contenedor (se reinicia en cada request/test).
 */
class CurrentWarehouse
{
    private const KEY = 'galpon.current_warehouse_id';

    public static function id(): ?int
    {
        if (app()->bound(self::KEY)) {
            return app(self::KEY);
        }

        $id = null;
        if (app()->bound('session.store') && request()->hasSession() && session()->has('warehouse_id')) {
            $id = (int) session('warehouse_id');
        }
        if (! $id && ($user = auth()->user())) {
            $id = $user->warehouses()->value('warehouses.id');
        }
        if (! $id) {
            $id = Warehouse::query()->where('active', true)->orderBy('id')->value('id');
        }

        if ($id) {
            app()->instance(self::KEY, $id);
        }

        return $id;
    }

    public static function set(int $id): void
    {
        session(['warehouse_id' => $id]);
        app()->instance(self::KEY, $id);
    }
}
