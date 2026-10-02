<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cotización del dólar por día (la vigente es la de fecha más reciente hasta hoy). */
class ExchangeRate extends Model
{
    use Auditable;

    public const SOURCES = ['BNA' => 'Banco Nación (oficial)', 'BCRA' => 'BCRA (Com. A 3500)', 'MEP' => 'Dólar MEP', 'manual' => 'Otra / manual'];

    protected $fillable = ['date', 'currency', 'buy', 'sell', 'source', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'buy' => 'decimal:4', 'sell' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function current(string $currency = 'USD'): ?self
    {
        return static::query()->where('currency', $currency)->whereDate('date', '<=', today()->toDateString())
            ->latest('date')->first();
    }
}
