<?php

namespace App\Models\Scopes;

use App\Support\CurrentWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtro de galpón: toda consulta de un modelo con BelongsToWarehouse trae sólo lo del galpón activo.
 * Sin galpón activo (consola con varios galpones, páginas públicas) no filtra: esas partes buscan por
 * códigos secretos o recorren los galpones uno por uno (CurrentWarehouse::forEach).
 */
class WarehouseScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! \App\Support\Tenancy::ready()) {
            return;
        }
        $id = CurrentWarehouse::id();
        if ($id !== null) {
            $builder->where($model->qualifyColumn('warehouse_id'), $id);
        }
    }
}
