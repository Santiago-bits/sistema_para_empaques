<?php

namespace App\Services\Devices;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Balanza conectada a través de un puente local: un pequeño programa en la PC de
 * la balanza publica cada lectura por la API (otro endpoint) y se guarda en cache
 * con la clave `scale:last:{estación}`. Este lector sólo consulta esa última lectura.
 *
 * Formato esperado en cache: ['weight' => 18.45, 'unit' => 'kg', 'stable' => true, 'read_at' => ISO-8601].
 */
class ApiScaleReader implements ScaleReader
{
    /** Una lectura más vieja que esto se informa como "desactualizada". */
    public const STALE_SECONDS = 10;

    public static function cacheKey(string $station): string
    {
        return 'scale:last:'.$station;
    }

    public function driver(): string
    {
        return 'api';
    }

    public function read(string $station): ?array
    {
        $raw = Cache::get(self::cacheKey($station));
        if (! is_array($raw) || ! isset($raw['weight']) || ! is_numeric($raw['weight'])) {
            return null;
        }

        $readAt = null;
        try {
            $readAt = isset($raw['read_at']) ? Carbon::parse($raw['read_at']) : null;
        } catch (\Throwable) {
            $readAt = null;
        }

        return [
            'weight' => round((float) $raw['weight'], 2),
            'unit' => (string) ($raw['unit'] ?? 'kg'),
            'stable' => (bool) ($raw['stable'] ?? true),
            'read_at' => $readAt?->toIso8601String(),
            'stale' => $readAt === null || $readAt->diffInSeconds(now(), true) > self::STALE_SECONDS,
        ];
    }
}
