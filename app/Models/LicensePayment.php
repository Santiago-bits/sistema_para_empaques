<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago de un empaque cliente por el uso del sistema. Nunca se borra: se anula con motivo. */
class LicensePayment extends Model
{
    use Auditable;

    public const METHODS = [
        'transfer' => 'Transferencia',
        'cash' => 'Efectivo',
        'mercadopago' => 'Mercado Pago',
        'check' => 'Cheque',
        'card' => 'Tarjeta',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'license_id', 'amount', 'currency', 'paid_at', 'months', 'period_from', 'period_to', 'method', 'reference', 'notes',
        'recorded_by', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'months' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
