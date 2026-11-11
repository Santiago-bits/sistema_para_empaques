<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Asociación de citricultores: común a todo el sistema; cada galpón pertenece a una o a varias. */
/**
 * EN DESUSO: las asociaciones de citricultores se sacaron del sistema. El modelo queda sólo para que el historial
 * de cambios (auditoría) muestre los registros viejos; las tablas quedan en la base sin uso.
 */
class Association extends Model
{
    use Auditable;

    protected $fillable = ['name', 'short_name', 'cuit', 'locality', 'province', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\CurrentWarehouse::forgetAssociations());
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class)->withTimestamps();
    }
}
