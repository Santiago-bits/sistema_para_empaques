<?php

namespace App\Services\Arca;

use Illuminate\Support\Carbon;

/**
 * Resultado de una operación con ARCA. request/response se guardan ya sanitizados.
 *
 * $uncertain: se envió la solicitud pero no llegó respuesta (corte, timeout). ARCA pudo haberlo
 * autorizado igual, así que NO se trata como rechazo: el comprobante queda pendiente hasta
 * verificarlo con FECompConsultar (si se reenviara a ciegas podría quedar duplicado en ARCA).
 */
final class ArcaResult
{
    public function __construct(
        public readonly bool $approved,
        public readonly ?int $number = null,
        public readonly ?string $cae = null,
        public readonly ?Carbon $caeExpiresOn = null,
        public readonly ?string $error = null,
        public readonly array $request = [],
        public readonly array $response = [],
        public readonly string $operation = 'FECAESolicitar',
        public readonly bool $uncertain = false,
    ) {
    }

    public static function failure(string $error, array $request = [], array $response = [], string $operation = 'FECAESolicitar'): self
    {
        return new self(false, error: $error, request: $request, response: $response, operation: $operation);
    }

    public static function uncertain(string $error, int $number, array $request = []): self
    {
        return new self(false, number: $number, error: $error, request: $request, uncertain: true);
    }
}
