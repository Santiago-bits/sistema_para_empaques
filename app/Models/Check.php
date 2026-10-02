<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cheque de terceros (recibido) o propio (emitido), físico o electrónico (e-cheq). */
class Check extends Model
{
    use Auditable, BelongsToWarehouse, SoftDeletes;

    public const KINDS = ['third_party' => 'De terceros', 'own' => 'Propio'];

    public const STATUSES = [
        'in_portfolio' => ['En cartera', 'blue'],
        'deposited' => ['Depositado', 'cyan'],
        'cashed' => ['Cobrado', 'emerald'],
        'endorsed' => ['Endosado', 'violet'],
        'issued' => ['Emitido', 'amber'],
        'paid' => ['Debitado', 'emerald'],
        'rejected' => ['Rechazado / devuelto', 'red'],
        'voided' => ['Anulado', 'zinc'],
    ];

    /** Transiciones válidas por tipo de cheque. */
    public const TRANSITIONS = [
        'third_party' => [
            'in_portfolio' => ['deposited', 'cashed', 'endorsed', 'rejected', 'voided'],
            'deposited' => ['cashed', 'rejected'],
            'endorsed' => ['rejected'],
            'cashed' => [],
            'rejected' => [],
            'voided' => [],
        ],
        'own' => [
            'issued' => ['paid', 'rejected', 'voided'],
            'paid' => [],
            'rejected' => [],
            'voided' => [],
        ],
    ];

    protected $fillable = [
        'warehouse_id', 'kind', 'electronic', 'bank', 'number', 'issuer_name', 'issuer_cuit', 'amount', 'issued_on',
        'payment_date', 'status', 'received_from_type', 'received_from_id', 'delivered_to_type', 'delivered_to_id',
        'status_date', 'notes', 'version', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'electronic' => 'boolean', 'amount' => 'decimal:2', 'issued_on' => 'date', 'payment_date' => 'date',
            'status_date' => 'date', 'version' => 'integer',
        ];
    }

    public function receivedFrom(): MorphTo
    {
        return $this->morphTo();
    }

    public function deliveredTo(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'zinc';
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->kind][$this->status] ?? [], true);
    }

    /** Días hasta la fecha de cobro (negativo = ya se puede cobrar / vencido). */
    public function daysToPayment(): int
    {
        return (int) today()->diffInDays($this->payment_date, false);
    }

    /** Cheques que todavía representan dinero pendiente. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['in_portfolio', 'deposited', 'issued']);
    }
}
