<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Devices\ApiScaleReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Lecturas enviadas por dispositivos: balanzas (scale:write) y sensores de cámaras (sensors:write). */
class DeviceController extends ApiController
{
    /** Última lectura de una balanza; el modo escaneo la consulta si el driver de balanza es «api». */
    public function scale(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'production.scan');
        $data = $request->validate([
            'station' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'weight' => ['required', 'numeric', 'min:0', 'max:5000000'],
            'unit' => ['nullable', 'in:kg,g'],
            'stable' => ['nullable', 'boolean'],
        ]);

        $weight = ($data['unit'] ?? 'kg') === 'g' ? $data['weight'] / 1000 : (float) $data['weight'];
        if ($weight > 5000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['weight' => 'El peso supera el máximo de 5000 kg.']);
        }
        $reading = ['weight' => round($weight, 3), 'unit' => 'kg', 'stable' => (bool) ($data['stable'] ?? true), 'read_at' => now()->toIso8601String()];
        Cache::put(ApiScaleReader::cacheKey($data['station']), $reading, now()->addMinutes(5));

        return response()->json(['data' => ['station' => $data['station']] + $reading], 201);
    }
}
