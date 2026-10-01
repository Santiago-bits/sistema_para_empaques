<?php

namespace App\Enums;

enum ArcaMode: string
{
    case Simulation = 'simulation';
    case Homologation = 'homologation';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Simulation => 'Simulación (sin conexión)',
            self::Homologation => 'Homologación (testing ARCA)',
            self::Production => 'Producción (comprobantes reales)',
        };
    }
}
