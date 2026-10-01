<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTarget extends Model
{
    use Auditable;

    protected $fillable = [
        'period', 'production_line_id', 'shift_id', 'target_kg', 'target_crates',
    ];

    protected function casts(): array
    {
        return [
            'target_kg' => 'decimal:2',
        ];
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
