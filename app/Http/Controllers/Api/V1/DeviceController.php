<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ColdRoomService;
use App\Services\Devices\ApiScaleReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function sensor(Request $request, ColdRoomService $rooms): JsonResponse
    {
        $this->requirePermission($request, 'cold_rooms.manage');
        $data = $request->validate([
            'sensor_key' => ['required', 'string', 'max:60'],
            'temperature' => ['required', 'numeric', 'between:-60,60'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString(), 'after:'.now()->subDays(2)->toDateTimeString()],
        ]);

        $room = $rooms->findBySensorKey($data['sensor_key']);
        abort_if($room === null, 404, 'No hay una cámara activa con ese sensor.');

        $record = $rooms->recordReading($room, (float) $data['temperature'], isset($data['humidity']) ? (float) $data['humidity'] : null,
            'sensor', isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : null);

        return response()->json(['data' => [
            'cold_room' => $room->name, 'temperature' => (float) $record->temperature, 'humidity' => $record->humidity !== null ? (float) $record->humidity : null,
            'out_of_range' => (bool) $record->out_of_range, 'recorded_at' => $record->recorded_at->toIso8601String(),
        ]], 201);
    }
}
