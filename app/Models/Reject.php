<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reject extends Model
{
    use Auditable;

    protected $fillable = [
        'crate_id', 'lot_id', 'variety_id', 'size_id', 'packer_id', 'reason_id', 'quality_control_id',
        'weight', 'user_id', 'rejected_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'rejected_at' => 'datetime',
        ];
    }

    public function crate(): BelongsTo
    {
        return $this->belongsTo(Crate::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
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

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Reason::class);
    }

    public function qualityControl(): BelongsTo
    {
        return $this->belongsTo(QualityControl::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
