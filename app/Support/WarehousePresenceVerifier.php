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
        if (Tenancy::ready() && ($id = CurrentWarehouse::id()) !== null) {
            if (Tenancy::isTenantTable($name)) {
                $query->where($name.'.warehouse_id', $id);
            } elseif ($name === 'roles') {
                // Roles del sistema (comunes) o del propio galpón.
                $query->where(fn ($q) => $q->whereNull('roles.warehouse_id')->orWhere('roles.warehouse_id', $id));
            }
        }

        return $query;
    }
}
