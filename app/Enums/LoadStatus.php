<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum LoadStatus: string
{
    use HasTransitions;

    case Draft = 'draft';
    case Closed = 'closed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'En armado',
            self::Closed => 'Cerrada',
            self::Dispatched => 'Despachada',
            self::Delivered => 'Entregada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Closed => 'blue',
            self::Dispatched => 'cyan',
            self::Delivered => 'emerald',
            self::Cancelled => 'zinc',
        };
    }

    public static function transitions(): array
    {
        return [
            // Reabrir una carga cerrada (Closed → Draft) requiere permiso especial y queda auditado.
            self::Draft->value => [self::Closed, self::Cancelled],
            self::Closed->value => [self::Dispatched, self::Draft],
            self::Dispatched->value => [self::Delivered],
            self::Delivered->value => [],
            self::Cancelled->value => [],
        ];
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
