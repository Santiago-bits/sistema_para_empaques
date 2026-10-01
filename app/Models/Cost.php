<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Cost extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'supplies' => 'Insumos',
        'transport' => 'Transporte',
        'labor' => 'Mano de obra',
        'maintenance' => 'Mantenimiento',
        'other' => 'Otros',
    ];

    protected $fillable = [
        'category', 'description', 'amount', 'currency', 'date', 'costable_type', 'costable_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
