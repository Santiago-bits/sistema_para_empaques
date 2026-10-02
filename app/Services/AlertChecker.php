<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\LoadStatus;
use App\Models\ColdRoom;
use App\Models\Crate;
use App\Models\Driver;
use App\Models\Load;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Supply;
use App\Models\TemperatureRecord;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Evalúa periódicamente las condiciones de alerta (comando galpon:check-alerts).
 * Sólo evalúa los tipos habilitados en Configuración → Alertas y cuyos módulos estén
 * activos. Si la condición desaparece, la alerta se resuelve sola.
 */
class AlertChecker
{
    /** Tipo de alerta => módulo del que depende. */
    public const CHECKS = [
        'stock_low' => 'supplies',
        'load_pending' => 'loads',
        'document_expiring' => 'catalogs',
        'maintenance_due' => 'maintenance',
        'crates_unprocessed' => 'crates',
        'temperature' => 'cold_rooms',
        'production_low' => 'production',
    ];

    /** Jornada operativa usada para el objetivo proporcional de producción. */
    private const WORKDAY_START = 6;

    private const WORKDAY_END = 22;

    public function __construct(private readonly AlertService $alerts, private readonly ModuleService $modules)
    {
    }

    /**
     * @return array<string, array{status: string, active?: int, resolved?: int, error?: string}>
     */
    public function run(): array
    {
        $enabled = (array) setting('alerts.enabled', []);
        $summary = [];

        foreach (self::CHECKS as $type => $module) {
            if (! ($enabled[$type] ?? false)) {
                $summary[$type] = ['status' => 'disabled'];
                continue;
            }
            if (! $this->modules->enabled($module)) {
                $summary[$type] = ['status' => 'module_disabled'];
                continue;
            }

            try {
                $method = 'check'.str_replace('_', '', ucwords($type, '_'));
                $active = $this->{$method}();
                $resolved = $this->alerts->resolveStale($type, $active);
                $summary[$type] = ['status' => 'ok', 'active' => count($active), 'resolved' => $resolved];
            } catch (Throwable $e) {
                // Un chequeo con problemas no impide evaluar el resto.
                report($e);
                $summary[$type] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $summary;
    }

    /** @return list<string> */
    protected function checkStockLow(): array
    {
        $active = [];
        Supply::query()->where('active', true)->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')
            ->lazyById(200)
            ->each(function (Supply $supply) use (&$active) {
                $fingerprint = 'stock_low:supply:'.$supply->id;
                $this->alerts->raise(
                    'stock_low',
                    'Stock bajo: '.$supply->name,
                    'Quedan '.num($supply->stock, 2).' '.$supply->unit.' (mínimo '.num($supply->min_stock, 2).' '.$supply->unit.').',
                    $supply,
                    (float) $supply->stock <= 0 ? 'critical' : 'warning',
                    $fingerprint,
                );
                $active[] = $fingerprint;
            });

        return $active;
    }

    /** @return list<string> */
    protected function checkLoadPending(): array
    {
        $hours = max(1, (int) setting('alerts.load_pending_hours', 24));
        $limit = now()->subHours($hours);
        $active = [];

        Load::query()
            ->where(function ($q) use ($limit) {
                $q->where(fn ($d) => $d->where('status', LoadStatus::Draft->value)->where('created_at', '<=', $limit))
                    ->orWhere(fn ($c) => $c->where('status', LoadStatus::Closed->value)
                        ->where(fn ($w) => $w->where('closed_at', '<=', $limit)
                            ->orWhere(fn ($n) => $n->whereNull('closed_at')->where('created_at', '<=', $limit))));
            })
            ->lazyById(200)
            ->each(function (Load $load) use (&$active, $hours) {
                $fingerprint = 'load_pending:load:'.$load->id;
                $closed = $load->status === LoadStatus::Closed;
                $this->alerts->raise(
                    'load_pending',
                    'Carga '.$load->number.' pendiente',
                    $closed
                        ? 'La carga está cerrada y sin despachar hace más de '.$hours.' h.'
                        : 'La carga está en armado hace más de '.$hours.' h.',
                    $load,
                    'warning',
                    $fingerprint,
                );
                $active[] = $fingerprint;
            });

        return $active;
    }

    /** @return list<string> */
    protected function checkDocumentExpiring(): array
    {
        $days = max(0, (int) setting('alerts.document_expiring_days', 15));
        $today = today();
        $active = [];

        Driver::query()->where('active', true)->whereNotNull('license_expires_on')
            ->whereDate('license_expires_on', '<=', $today->copy()->addDays($days))
            ->lazyById(200)
            ->each(function (Driver $driver) use (&$active, $today) {
                $fingerprint = 'document_expiring:driver:'.$driver->id;
                $expired = $driver->license_expires_on->lt($today);
                $this->alerts->raise(
                    'document_expiring',
                    ($expired ? 'Licencia vencida: ' : 'Licencia por vencer: ').$driver->full_name,
                    'Licencia de conducir '.($expired ? 'vencida el ' : 'vence el ').fdate($driver->license_expires_on).'.',
                    $driver,
                    $expired ? 'critical' : 'warning',
                    $fingerprint,
                );
                $active[] = $fingerprint;
            });

        // Camiones: seguro, VTV / RTO y habilitación SENASA.
        $truckDocs = ['insurance_expires_on' => 'Seguro', 'vtv_expires_on' => 'VTV / RTO', 'senasa_expires_on' => 'Habilitación SENASA'];
        foreach ($truckDocs as $column => $label) {
            \App\Models\Truck::query()->where('active', true)->whereNotNull($column)
                ->whereDate($column, '<=', $today->copy()->addDays($days))
                ->lazyById(200)
                ->each(function (\App\Models\Truck $truck) use (&$active, $today, $column, $label) {
                    $fingerprint = 'document_expiring:truck:'.$column.':'.$truck->id;
                    $expired = $truck->{$column}->lt($today);
                    $this->alerts->raise(
                        'document_expiring',
                        $label.' '.($expired ? 'vencido' : 'por vencer').': camión '.$truck->plate,
                        $label.' del camión '.$truck->plate.' '.($expired ? 'venció el ' : 'vence el ').fdate($truck->{$column}).'.',
                        $truck,
                        $expired ? 'critical' : 'warning',
                        $fingerprint,
                    );
                    $active[] = $fingerprint;
                });
        }

        return $active;
    }

    /** @return list<string> */
    protected function checkMaintenanceDue(): array
    {
        $days = max(0, (int) setting('alerts.maintenance_days', 7));
        $today = today();
        $active = [];

        Machine::query()->whereNotNull('next_maintenance_on')
            ->where('status', '!=', 'out_of_service')
            ->whereDate('next_maintenance_on', '<=', $today->copy()->addDays($days))
            ->lazyById(200)
            ->each(function (Machine $machine) use (&$active, $today) {
                $fingerprint = 'maintenance_due:machine:'.$machine->id;
                $overdue = $machine->next_maintenance_on->lt($today);
                $this->alerts->raise(
                    'maintenance_due',
                    ($overdue ? 'Mantenimiento vencido: ' : 'Mantenimiento próximo: ').$machine->name,
                    'Mantenimiento programado para el '.fdate($machine->next_maintenance_on).'.',
                    $machine,
                    $overdue ? 'critical' : 'warning',
                    $fingerprint,
                );
                $active[] = $fingerprint;
            });

        return $active;
    }

    /** @return list<string> */
    protected function checkCratesUnprocessed(): array
    {
        $hours = max(1, (int) setting('alerts.crates_unprocessed_hours', 12));
        // Una sola alerta agregada (puede haber miles de cajones).
        $count = Crate::query()->where('status', CrateStatus::Registered->value)
            ->where('created_at', '<=', now()->subHours($hours))
            ->count();

        if ($count === 0) {
            return [];
        }

        $fingerprint = 'crates_unprocessed';
        $this->alerts->raise(
            'crates_unprocessed',
            num($count).' '.($count === 1 ? 'cajón sin procesar' : 'cajones sin procesar'),
            'Cajones registrados hace más de '.$hours.' h que todavía no fueron procesados.',
            null,
            'warning',
            $fingerprint,
        );

        return [$fingerprint];
    }

    /** @return list<string> */
    protected function checkTemperature(): array
    {
        $active = [];

        ColdRoom::query()->where('active', true)->get()->each(function (ColdRoom $room) use (&$active) {
            $last = TemperatureRecord::query()->where('cold_room_id', $room->id)
                ->latest('recorded_at')->latest('id')->first();

            if (! $last || ! $last->out_of_range) {
                return;
            }

            $fingerprint = self::temperatureFingerprint($room);
            $this->alerts->raise(
                'temperature',
                'Temperatura fuera de rango: '.$room->name,
                self::temperatureMessage($room, (float) $last->temperature, $last->humidity !== null ? (float) $last->humidity : null, $last->recorded_at),
                $room,
                'critical',
                $fingerprint,
            );
            $active[] = $fingerprint;
        });

        return $active;
    }

    /** @return list<string> */
    protected function checkProductionLow(): array
    {
        $now = now();
        $fingerprint = 'production_low:'.$now->toDateString();
        $target = (float) setting('production.target_daily_kg', 0);

        // Fuera de la jornada o sin objetivo no tiene sentido evaluar.
        if ($target <= 0 || $now->hour < self::WORKDAY_START + 2 || $now->hour >= self::WORKDAY_END) {
            return [];
        }

        $start = $now->copy()->startOfDay()->setTime(self::WORKDAY_START, 0);
        $elapsed = $start->diffInMinutes($now) / 60;
        $expected = $target * min(1, $elapsed / (self::WORKDAY_END - self::WORKDAY_START));

        $produced = (float) ProductionRecord::query()->whereNull('voided_at')
            ->whereBetween('recorded_at', [$now->copy()->startOfDay(), $now])
            ->sum('weight');

        if ($expected <= 0 || $produced >= $expected * 0.5) {
            return [];
        }

        $this->alerts->raise(
            'production_low',
            'Producción baja hoy',
            'Se produjeron '.kg($produced, 0).' y a esta hora se esperaban al menos '.kg($expected * 0.5, 0)
                .' (50 % del objetivo proporcional de '.kg($expected, 0).').',
            null,
            'warning',
            $fingerprint,
        );

        return [$fingerprint];
    }

    public static function temperatureFingerprint(ColdRoom $room): string
    {
        return 'temperature:cold_room:'.$room->id;
    }

    public static function temperatureMessage(ColdRoom $room, float $temperature, ?float $humidity, ?Carbon $at): string
    {
        $text = 'Lectura de '.num($temperature, 1).' °C';
        if ($humidity !== null) {
            $text .= ' / '.num($humidity, 0).' % HR';
        }

        return $text.' (rango '.num($room->temp_min, 1).' a '.num($room->temp_max, 1).' °C)'
            .($at ? ' el '.fdate($at, true) : '').'.';
    }
}
