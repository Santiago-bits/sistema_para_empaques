<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ColdRoom;
use App\Models\TemperatureRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cámaras frigoríficas: registro de lecturas (manuales o de sensores vía API) y alertas
 * de temperatura/humedad fuera de rango. Mientras la alerta siga abierta no se duplica;
 * si estaba resuelta y vuelve a haber una lectura fuera de rango, se reabre.
 */
class ColdRoomService
{
    public const SOURCES = ['manual' => 'Manual', 'sensor' => 'Sensor'];

    public function __construct(private readonly AlertService $alerts)
    {
    }

    public static function alertFingerprint(ColdRoom $room): string
    {
        return 'temperature:cold_room:'.$room->getKey();
    }

    public function recordReading(
        ColdRoom $room,
        float $temp,
        ?float $humidity = null,
        string $source = 'manual',
        ?Carbon $at = null,
    ): TemperatureRecord {
        if (! array_key_exists($source, self::SOURCES)) {
            throw new BusinessException('Origen de lectura inválido.');
        }
        if (! $room->active) {
            throw new BusinessException("La cámara {$room->name} está inactiva: no se registran lecturas.");
        }
        if ($temp < -60 || $temp > 60) {
            throw new BusinessException('La temperatura informada está fuera del rango físico posible (-60 °C a 60 °C).');
        }
        if ($humidity !== null && ($humidity < 0 || $humidity > 100)) {
            throw new BusinessException('La humedad debe estar entre 0 y 100 %.');
        }

        $at ??= now();
        $outOfRange = $room->isOutOfRange($temp, $humidity);

        $record = DB::transaction(fn () => TemperatureRecord::query()->create([
            'cold_room_id' => $room->id,
            'temperature' => $temp,
            'humidity' => $humidity,
            'source' => $source,
            'out_of_range' => $outOfRange,
            'user_id' => auth()->id(),
            'recorded_at' => $at,
        ]));

        if ($outOfRange) {
            $this->alerts->raise(
                'temperature',
                "Cámara {$room->name}: lectura fuera de rango",
                $this->alertMessage($room, $temp, $humidity, $at),
                $room,
                'critical',
                self::alertFingerprint($room),
            );
        }

        return $record;
    }

    /** Busca la cámara activa asociada a un sensor (para la API de lecturas). */
    public function findBySensorKey(string $sensorKey): ?ColdRoom
    {
        return ColdRoom::query()->where('sensor_key', $sensorKey)->where('active', true)->first();
    }

    /**
     * Datos para el gráfico de lecturas de las últimas $hours horas.
     *
     * @return array{labels: list<string>, temperature: list<float>, humidity: list<float|null>, min: float, max: float}
     */
    public function chartData(ColdRoom $room, int $hours = 48): array
    {
        $rows = TemperatureRecord::query()->where('cold_room_id', $room->id)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at')->limit(2000)
            ->get(['temperature', 'humidity', 'recorded_at']);

        return [
            'labels' => $rows->map(fn ($r) => $r->recorded_at->format('d/m H:i'))->all(),
            'temperature' => $rows->map(fn ($r) => (float) $r->temperature)->all(),
            'humidity' => $rows->map(fn ($r) => $r->humidity !== null ? (float) $r->humidity : null)->all(),
            'min' => (float) $room->temp_min,
            'max' => (float) $room->temp_max,
        ];
    }

    /**
     * Última lectura por cámara (para listados y dashboard).
     *
     * @return Collection<int, TemperatureRecord> indexado por cold_room_id
     */
    public function latestReadings(?array $roomIds = null): Collection
    {
        $ids = TemperatureRecord::query()
            ->when($roomIds !== null, fn ($q) => $q->whereIn('cold_room_id', $roomIds))
            ->selectRaw('MAX(id) as id')->groupBy('cold_room_id')->pluck('id');

        return TemperatureRecord::query()->whereIn('id', $ids)->get()->keyBy('cold_room_id');
    }

    /**
     * Resumen para el dashboard: cámaras activas, cuántas tienen la última lectura fuera de rango y sin lecturas recientes.
     *
     * @return array{rooms: int, out_of_range: int, stale: int}
     */
    public function summary(int $staleHours = 6): array
    {
        $rooms = ColdRoom::query()->where('active', true)->pluck('id');
        $latest = $this->latestReadings($rooms->all());

        return [
            'rooms' => $rooms->count(),
            'out_of_range' => $latest->where('out_of_range', true)->count(),
            'stale' => $rooms->filter(fn ($id) => ! isset($latest[$id]) || $latest[$id]->recorded_at->lt(now()->subHours($staleHours)))->count(),
        ];
    }

    private function alertMessage(ColdRoom $room, float $temp, ?float $humidity, Carbon $at): string
    {
        $msg = sprintf('Lectura %s: %s °C (rango %s a %s °C)', $at->format('d/m/Y H:i'), num($temp, 1), num($room->temp_min, 1), num($room->temp_max, 1));
        if ($humidity !== null) {
            $msg .= sprintf(', humedad %s %%', num($humidity, 1));
            if ($room->humidity_min !== null || $room->humidity_max !== null) {
                $msg .= sprintf(' (rango %s a %s %%)', $room->humidity_min !== null ? num($room->humidity_min, 1) : '—', $room->humidity_max !== null ? num($room->humidity_max, 1) : '—');
            }
        }

        return $msg.'.';
    }
}
