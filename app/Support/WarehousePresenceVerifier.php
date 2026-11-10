<?php

namespace App\Support;

use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Reglas «unique» y «exists» de validación sobre tablas de un galpón (Tenancy::TABLES): sólo miran el
 * galpón activo. Así un código puede repetirse en otro galpón, y nadie puede elegir (por id) un productor,
 * cliente o camión de otro galpón: para la validación, simplemente no existe.
 */
class WarehousePresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        $query = parent::table($table);
        $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
        if (Tenancy::isTenantTable($name) && ($id = CurrentWarehouse::id()) !== null) {
            $query->where($name.'.warehouse_id', $id);
        }

        return $query;
    }
}
