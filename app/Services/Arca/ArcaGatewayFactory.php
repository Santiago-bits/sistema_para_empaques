<?php

namespace App\Services\Arca;

use App\Exceptions\BusinessException;

/**
 * Elige el gateway según setting('arca.mode'). Regla de oro: en cualquier entorno que
 * no sea producción, nunca se usa el web service de producción (no se emiten comprobantes reales).
 */
class ArcaGatewayFactory
{
    public function make(?string $mode = null): ArcaGateway
    {
        $mode ??= (string) setting('arca.mode', 'simulation');

        if ($mode === 'production' && ! app()->environment('production')) {
            throw new BusinessException('No se envían comprobantes reales fuera del entorno de producción. Usá simulación u homologación.');
        }

        return match ($mode) {
            'homologation', 'production' => new WsfeGateway($mode),
            default => new SimulationGateway,
        };
    }
}
