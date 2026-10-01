<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum PalletStatus: string
{
    use HasTransitions;

    case Empty = 'empty';
    case Received = 'received';
    case WithProduct = 'with_product';
    case Reserved = 'reserved';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Vacío',
            self::Received => 'Ingresado',
            self::WithProduct => 'Con producto',
            self::Reserved => 'Reservado',
            self::Loaded => 'Cargado',
            self::Dispatched => 'Despachado',
            self::Voided => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Empty => 'slate',
            self::Received => 'blue',
            self::WithProduct => 'emerald',
            self::Reserved => 'violet',
            self::Loaded => 'indigo',
            self::Dispatched => 'cyan',
            self::Voided => 'zinc',
        };
    }

    public static function transitions(): array
    {
        return [
            self::Empty->value => [self::Received, self::WithProduct, self::Voided],
            self::Received->value => [self::WithProduct, self::Empty, self::Voided],
            self::WithProduct->value => [self::Reserved, self::Empty, self::Voided],
            self::Reserved->value => [self::Loaded, self::WithProduct],
            self::Loaded->value => [self::Dispatched, self::Reserved],
            self::Dispatched->value => [],
            self::Voided->value => [],
        ];
    }
}
