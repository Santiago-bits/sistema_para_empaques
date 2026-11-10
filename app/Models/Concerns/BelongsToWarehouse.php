<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WarehouseScope;
use App\Models\Warehouse;
use App\Support\CurrentWarehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Multigalpón: el registro pertenece a un galpón.
 * - Al crearlo se completa warehouse_id con el galpón activo.
 * - Toda consulta trae sólo lo del galpón activo (WarehouseScope).
 * - Nunca se puede mover un registro a otro galpón.
 */
trait BelongsToWarehouse
{
    public static function bootBelongsToWarehouse(): void
    {
        static::addGlobalScope(new WarehouseScope);

        static::creating(function ($model) {
            if (! \App\Support\Tenancy::ready() && ! in_array($model->getTable(), \App\Support\Tenancy::LEGACY_TABLES, true)) {
                return; // base todavía sin actualizar: la columna aún no existe
            }
            if (empty($model->warehouse_id)) {
                $model->warehouse_id = CurrentWarehouse::id();
                // Sólo la auditoría admite registros sin galpón (acciones del Super Administrador sobre todo el sistema).
                if ($model->warehouse_id === null && ! ($model->warehouseOptional ?? false)) {
                    throw new LogicException('No hay un galpón activo para guardar '.class_basename($model).'.');
                }
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('warehouse_id') && $model->getOriginal('warehouse_id') !== null) {
                throw new LogicException('Un registro no puede pasar de un galpón a otro.');
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
