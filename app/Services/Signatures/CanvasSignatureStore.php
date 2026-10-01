<?php

namespace App\Services\Signatures;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Firma dibujada en un <canvas> y enviada como data:image/png;base64,...
 * Se guarda en storage/app/private/signatures/AAAA/MM (nunca accesible por URL pública).
 */
class CanvasSignatureStore implements DeliverySignatureStore
{
    public const MAX_BYTES = 512 * 1024;

    private const PREFIX = 'data:image/png;base64,';

    public function store(string $payload): string
    {
        if (! str_starts_with($payload, self::PREFIX)) {
            throw new BusinessException('La firma debe ser una imagen PNG.');
        }

        $base64 = substr($payload, strlen(self::PREFIX));
        if (strlen($base64) > (int) ceil(self::MAX_BYTES * 4 / 3) + 4) {
            throw new BusinessException('La imagen de la firma es demasiado grande.');
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            throw new BusinessException('La firma no es válida.');
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new BusinessException('La imagen de la firma es demasiado grande.');
        }
        // Verificación del contenido real (no confiar en el prefijo): firma PNG + dimensiones razonables.
        if (! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw new BusinessException('La firma no es una imagen PNG válida.');
        }
        $info = @getimagesizefromstring($binary);
        if (! $info || ($info[2] ?? null) !== IMAGETYPE_PNG || $info[0] < 10 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 4000) {
            throw new BusinessException('La firma no es una imagen PNG válida.');
        }

        $path = 'signatures/'.now()->format('Y/m').'/'.Str::random(40).'.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }
}
