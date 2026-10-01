<?php

namespace App\Models;

use App\Enums\CrateStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Crate extends Model
{
    use Auditable, BelongsToWarehouse, HasFactory, HasStateHistory, SoftDeletes;

    public const QUALITY_STATUSES = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];

    /** Bloqueo optimista: se incrementa en cada cambio de estado o edición crítica. */
    protected $attributes = ['version' => 0];

    protected $fillable = [
        'warehouse_id', 'season_id', 'code', 'barcode', 'pallet_id', 'lot_id', 'producer_id', 'owner_id',
        'variety_id', 'size_id', 'packer_id', 'shift_id', 'production_line_id', 'weight', 'status',
        'quality_status', 'location_id', 'current_load_id', 'processed_at', 'processed_by', 'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'processed_at' => 'datetime',
            'status' => CrateStatus::class,
        ];
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
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

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Packer::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function currentLoad(): BelongsTo
    {
        return $this->belongsTo(Load::class, 'current_load_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    public function qualityControls(): HasMany
    {
        return $this->hasMany(QualityControl::class);
    }

    public function rejects(): HasMany
    {
        return $this->hasMany(Reject::class);
    }

    public function loadAssignments(): HasMany
    {
        return $this->hasMany(LoadCrate::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(LocationMovement::class, 'movable')->orderBy('moved_at');
    }
}
