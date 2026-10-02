<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Caja (efectivo) de un galpón entre una apertura y su cierre con arqueo. */
class CashSession extends Model
{
    use Auditable, BelongsToWarehouse;

    protected $fillable = [
        'warehouse_id', 'open_warehouse_id', 'opened_at', 'opened_by', 'opening_balance', 'closed_at', 'closed_by',
        'expected_balance', 'counted_balance', 'difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_balance' => 'decimal:2',
            'expected_balance' => 'decimal:2', 'counted_balance' => 'decimal:2', 'difference' => 'decimal:2',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** @return array{in: float, out: float, balance: float} */
    public function totals(): array
    {
        $sums = $this->movements()->whereNull('voided_at')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as total_in")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as total_out")
            ->toBase()->first();
        $in = round((float) ($sums->total_in ?? 0), 2);
        $out = round((float) ($sums->total_out ?? 0), 2);

        return ['in' => $in, 'out' => $out, 'balance' => round((float) $this->opening_balance + $in - $out, 2)];
    }
}
