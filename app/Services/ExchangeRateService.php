<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Valor del dólar: una cotización por día; cargarla de nuevo para el mismo día la corrige (queda auditado).
 * Se carga a mano o se trae de internet (DolarApi: dólar oficial del Banco Nación o blue). Con la actualización
 * automática activada se trae sola cada AUTO_HOURS horas (programador de tareas o, si no hay cron, al abrir la pantalla).
 */
class ExchangeRateService
{
    /** Tipos que se pueden traer de internet => [endpoint, fuente que queda registrada]. */
    public const ONLINE_TYPES = [
        'oficial' => ['https://dolarapi.com/v1/dolares/oficial', 'BNA'],
        'blue' => ['https://dolarapi.com/v1/dolares/blue', 'BLUE'],
    ];

    public const TYPE_LABELS = ['oficial' => 'Dólar oficial (Banco Nación)', 'blue' => 'Dólar blue'];

    public const AUTO_HOURS = 6;

    private const LAST_FETCH_KEY = 'exchange:last-auto-fetch';

    /** Si el servicio falla, no se reintenta solo hasta pasado un rato (así la pantalla no espera en cada visita). */
    private const FAILED_KEY = 'exchange:auto-failed';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function save(array $data, ?User $by): ExchangeRate
    {
        $date = Carbon::parse($data['date'])->startOfDay();
        $values = [
            'sell' => round((float) $data['sell'], 4),
            'buy' => isset($data['buy']) ? round((float) $data['buy'], 4) : null,
            'source' => $data['source'] ?? null,
            'user_id' => $by?->id,
        ];

        return DB::transaction(function () use ($date, $values) {
            // whereDate: la columna puede guardarse con hora según el motor (SQLite / MySQL).
            $rate = ExchangeRate::query()->where('currency', 'USD')->whereDate('date', $date->toDateString())->lockForUpdate()->first();
            if ($rate) {
                $rate->update($values);

                return $rate;
            }

            return ExchangeRate::query()->create($values + ['date' => $date, 'currency' => 'USD']);
        });
    }

    /**
     * Trae la cotización de hoy (oficial del Banco Nación o blue) y la guarda como la del día.
     *
     * @throws BusinessException si el servicio no responde o devuelve datos inválidos
     */
    public function fetchOnline(string $type, ?User $by = null): ExchangeRate
    {
        [$url, $source] = self::ONLINE_TYPES[$type] ?? throw new BusinessException('Tipo de dólar no válido.');

        try {
            $data = Http::timeout(10)->acceptJson()->get($url)->throw()->json();
        } catch (Throwable) {
            throw new BusinessException('No se pudo consultar el valor del dólar en este momento. Probá de nuevo en unos minutos o cargalo a mano.');
        }

        $sell = (float) ($data['venta'] ?? 0);
        $buy = (float) ($data['compra'] ?? 0);
        if ($sell <= 0) {
            throw new BusinessException('El servicio de cotizaciones devolvió un valor inválido. Cargalo a mano.');
        }

        Cache::put(self::LAST_FETCH_KEY, now()->getTimestamp(), now()->addDays(7));

        return $this->save([
            'date' => today()->toDateString(),
            'sell' => $sell,
            'buy' => $buy > 0 && $buy <= $sell ? $buy : null,
            'source' => $source,
        ], $by);
    }

    /**
     * Si la actualización automática está activada y pasaron AUTO_HOURS desde la última, trae el tipo elegido.
     * Nunca rompe: si el servicio falla, se reintenta en la próxima pasada.
     */
    public function autoUpdate(): ?ExchangeRate
    {
        if (! self::autoEnabled() || ! $this->autoIsDue() || Cache::has(self::FAILED_KEY)) {
            return null;
        }

        try {
            return $this->fetchOnline(self::autoType());
        } catch (BusinessException) {
            Cache::put(self::FAILED_KEY, true, now()->addMinutes(30));

            return null;
        }
    }

    public function configureAuto(bool $enabled, string $type): void
    {
        $this->settings->set('treasury.exchange_auto', $enabled);
        $this->settings->set('treasury.exchange_type', array_key_exists($type, self::ONLINE_TYPES) ? $type : 'oficial');
        // Al activar o cambiar de tipo, la próxima pasada trae el valor enseguida.
        Cache::forget(self::LAST_FETCH_KEY);
        Cache::forget(self::FAILED_KEY);
    }

    public static function autoEnabled(): bool
    {
        return (bool) setting('treasury.exchange_auto', false);
    }

    public static function autoType(): string
    {
        $type = (string) setting('treasury.exchange_type', 'oficial');

        return array_key_exists($type, self::ONLINE_TYPES) ? $type : 'oficial';
    }

    public static function lastFetch(): ?Carbon
    {
        $ts = Cache::get(self::LAST_FETCH_KEY);

        return $ts ? Carbon::createFromTimestamp($ts, config('app.timezone')) : null;
    }

    private function autoIsDue(): bool
    {
        $last = self::lastFetch();

        return ! $last || $last->lte(now()->subHours(self::AUTO_HOURS));
    }
}
