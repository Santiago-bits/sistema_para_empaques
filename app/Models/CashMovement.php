<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'collection' => 'Cobro a cliente',
        'producer_payment' => 'Pago a productor',
        'freight' => 'Pago de flete',
        'provider_payment' => 'Pago a proveedor',
        'wages' => 'Sueldos y jornales',
        'advance' => 'Adelanto a personal',
        'expenses' => 'Gastos varios',
        'bank_deposit' => 'Depósito en banco',
        'bank_withdrawal' => 'Extracción del banco',
        'owner' => 'Aporte / retiro de socios',
        'other' => 'Otros',
    ];

    protected $fillable = [
        'cash_session_id', 'moved_at', 'direction', 'category', 'description', 'amount', 'account_movement_id', 'user_id',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return ['moved_at' => 'datetime', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function accountMovement(): BelongsTo
    {
        return $this->belongsTo(AccountMovement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
