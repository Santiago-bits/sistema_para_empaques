<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lot extends Model
{
    use Auditable, BelongsToWarehouse, HasStateHistory, SoftDeletes;

    public const STATUSES = ['open' => 'Abierto', 'closed' => 'Cerrado', 'voided' => 'Anulado'];

    protected $fillable = [
        'warehouse_id', 'season_id', 'code', 'date', 'producer_id', 'owner_id', 'variety_id', 'origin',
        'field', 'quantity', 'status', 'notes', 'created_by', 'container_type_id', 'kg_received', 'price_per_kg',
        'settled_at', 'settled_by', 'driver_id', 'bins', 'dtv_number',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kg_received' => 'decimal:2',
            'price_per_kg' => 'decimal:4',
            'settled_at' => 'datetime',
        ];
    }

    /** Chofer que trajo la fruta (planilla de ingresos). */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }

    /** Importe de compra al productor (kilos × precio), sin descontar la tasa de asociación. */
    public function purchaseAmount(): ?float
    {
        return $this->kg_received !== null && $this->price_per_kg !== null
            ? round((float) $this->kg_received * (float) $this->price_per_kg, 2)
            : null;
    }
}
