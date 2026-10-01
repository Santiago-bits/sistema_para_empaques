<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum InvoiceStatus: string
{
    use HasTransitions;

    case Draft = 'draft';
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Pending => 'Pendiente ARCA',
            self::Authorized => 'Autorizada (CAE)',
            self::Rejected => 'Rechazada',
            self::Voided => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Pending => 'amber',
            self::Authorized => 'emerald',
            self::Rejected => 'red',
            self::Voided => 'zinc',
        };
    }

    public static function transitions(): array
    {
        return [
            self::Draft->value => [self::Pending, self::Voided],
            self::Pending->value => [self::Authorized, self::Rejected],
            // Un comprobante rechazado puede corregirse y reintentarse.
            self::Rejected->value => [self::Pending, self::Draft, self::Voided],
            // Un comprobante autorizado sólo se anula con nota de crédito.
            self::Authorized->value => [],
            self::Voided->value => [],
        ];
    }
}
