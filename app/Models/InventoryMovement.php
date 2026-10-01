<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use Auditable;

    public const TYPES = ['in' => 'Ingreso', 'out' => 'Egreso', 'adjust' => 'Ajuste'];

    protected $fillable = [
        'supply_id', 'type', 'quantity', 'stock_after', 'unit_cost', 'provider_id', 'reference', 'notes',
        'user_id', 'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'moved_at' => 'datetime',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
