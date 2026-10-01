<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecord extends Model
{
    use Auditable, BelongsToWarehouse;

    protected $fillable = [
        'warehouse_id', 'crate_id', 'packer_id', 'variety_id', 'size_id', 'shift_id', 'production_line_id',
        'weight', 'weight_source', 'user_id', 'recorded_at', 'idempotency_key', 'authorized_by',
        'authorization_reason', 'voided_at', 'voided_by', 'void_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'recorded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function crate(): BelongsTo
    {
        return $this->belongsTo(Crate::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Packer::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(Variety::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
