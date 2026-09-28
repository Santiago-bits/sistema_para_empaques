<?php

namespace App\Enums;

/**
 * Estados posibles de un empaque.
 * El valor (string) es lo que se guarda en la columna `empaques.estado`.
 */
enum EstadoEmpaque: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Despachado = 'despachado';
    case Entregado = 'entregado';

    /**
     * Texto legible para mostrar en las vistas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProceso => 'En proceso',
            self::Despachado => 'Despachado',
            self::Entregado => 'Entregado',
        };
    }

    /**
     * Color de Bootstrap asociado a cada estado (para badges).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'secondary',
            self::EnProceso => 'warning',
            self::Despachado => 'primary',
            self::Entregado => 'success',
        };
    }
}
