<?php

namespace App\Models\Concerns;

use App\Models\Warehouse;
use App\Support\CurrentWarehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prepara la arquitectura multigalpón: completa warehouse_id con el galpón
 * activo de la sesión al crear el registro.
 */
trait BelongsToWarehouse
{
    public static function bootBelongsToWarehouse(): void
    {
        static::creating(function ($model) {
            if (empty($model->warehouse_id)) {
                $model->warehouse_id = CurrentWarehouse::id();
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
