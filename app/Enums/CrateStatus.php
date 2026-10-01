<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

/**
 * Ciclo de vida de un cajón. Las transiciones válidas están definidas acá
 * y son verificadas por StateTransitionService antes de cualquier cambio.
 */
enum CrateStatus: string
{
    use HasTransitions;

    case Registered = 'registered';
    case Processed = 'processed';
    case InControl = 'in_control';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reserved = 'reserved';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case Invoiced = 'invoiced';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrado',
            self::Processed => 'Procesado',
            self::InControl => 'En control',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Reserved => 'Asignado a carga',
            self::Loaded => 'Cargado',
            self::Dispatched => 'Despachado',
            self::Invoiced => 'Facturado',
            self::Voided => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered => 'slate',
            self::Processed => 'blue',
            self::InControl => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::Reserved => 'violet',
            self::Loaded => 'indigo',
            self::Dispatched => 'cyan',
            self::Invoiced => 'teal',
            self::Voided => 'zinc',
        };
    }

    /** @return array<string, list<self>> */
    public static function transitions(): array
    {
        return [
            self::Registered->value => [self::Processed, self::Voided],
            self::Processed->value => [self::InControl, self::Approved, self::Rejected, self::Reserved, self::Voided],
            self::InControl->value => [self::Approved, self::Rejected, self::Voided],
            self::Approved->value => [self::Reserved, self::InControl, self::Rejected, self::Voided],
            self::Rejected->value => [self::InControl, self::Voided],
            self::Reserved->value => [self::Loaded, self::Approved, self::Processed],
            self::Loaded->value => [self::Dispatched, self::Reserved],
            self::Dispatched->value => [self::Invoiced],
            self::Invoiced->value => [],
            // Anulado es terminal: reactivar requiere una operación especial autorizada.
            self::Voided->value => [],
        ];
    }

    /** Estados en los que un cajón puede agregarse a una carga. */
    public static function assignable(): array
    {
        return [self::Processed, self::Approved];
    }
}
