<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum RemitoStatus: string
{
    use HasTransitions;

    case Issued = 'issued';
    case Delivered = 'delivered';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitido',
            self::Delivered => 'Entregado',
            self::Voided => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'blue',
            self::Delivered => 'emerald',
            self::Voided => 'zinc',
        };
    }

    public static function transitions(): array
    {
        return [
            self::Issued->value => [self::Delivered, self::Voided],
            self::Delivered->value => [],
            self::Voided->value => [],
        ];
    }
}
