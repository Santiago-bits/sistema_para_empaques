<?php

namespace App\Services\Arca;

use Illuminate\Support\Carbon;

/** Resultado de una operación con ARCA. request/response se guardan ya sanitizados. */
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
    ) {
    }

    public static function failure(string $error, array $request = [], array $response = [], string $operation = 'FECAESolicitar'): self
    {
        return new self(false, error: $error, request: $request, response: $response, operation: $operation);
    }
}
