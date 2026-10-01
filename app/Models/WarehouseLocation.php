<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLocation extends Model
{
    use Auditable, BelongsToWarehouse;

    public const TYPES = [
        'sector' => 'Sector',
        'aisle' => 'Pasillo',
        'rack' => 'Estantería',
        'cold_room' => 'Cámara',
        'zone' => 'Zona',
        'position' => 'Posición',
        'dispatch' => 'Zona de despacho',
    ];

    protected $fillable = [
        'warehouse_id', 'parent_id', 'type', 'code', 'name', 'capacity_pallets', 'map_x', 'map_y', 'map_w',
        'map_h', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'parent_id');
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class, 'location_id');
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class, 'location_id');
    }

    /** Ruta completa, p.ej. "Sector 1 › Pasillo A › A01". */
    public function path(): string
    {
        $names = [$this->name];
        $node = $this->parent;
        $guard = 0;
        while ($node && $guard++ < 10) {
            array_unshift($names, $node->name);
            $node = $node->parent;
        }

        return implode(' › ', $names);
    }
}
