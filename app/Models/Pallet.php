<?php

namespace App\Models;

use App\Enums\PalletStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pallet extends Model
{
    use Auditable, BelongsToWarehouse, HasFactory, HasStateHistory, SoftDeletes;

    protected $fillable = [
        'warehouse_id', 'season_id', 'code', 'barcode', 'lot_id', 'producer_id', 'owner_id', 'variety_id',
        'origin', 'received_at', 'quantity', 'gross_weight', 'status', 'location_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'gross_weight' => 'decimal:2',
            'status' => PalletStatus::class,
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function crates(): HasMany
    {
        return $this->hasMany(Crate::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(LocationMovement::class, 'movable')->orderBy('moved_at');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
