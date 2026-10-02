<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LoadStatus;
use App\Enums\PalletStatus;
use App\Models\Cost;
use App\Models\ProductionTarget;
use App\Models\Season;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas para dashboard, reportes, estadísticas y cierre diario.
 *
 * Reglas:
 *  - TODO se calcula en SQL (COUNT/SUM/GROUP BY): nunca se cargan miles de filas en PHP.
 *  - Producción válida = production_records con voided_at IS NULL.
 *  - Compatible con MySQL/MariaDB y SQLite (tests): las expresiones de fecha/hora
 *    dependientes del motor se generan con los helpers *Expr().
 *  - Semana/mes/año se arman en PHP a partir de los agregados diarios (pocas filas).
 *
 * Definiciones de indicadores:
 *  - kg procesados = SUM(weight) de producción válida en el período.
 *  - % merma = kg rechazados / (kg procesados + kg rechazados) × 100.
 *  - % aprovechamiento = 100 − % merma.
 *  - horas productivas = cantidad de franjas (día, hora) con al menos un registro.
 *  - tiempo de carga = closed_at − created_at de las cargas cerradas en el período.
 */
class ReportService
{
    /** Tiempo de cache del dashboard (segundos). */
    public const DASHBOARD_TTL = 60;

    /** Comprobantes que restan (notas de crédito A/B/C). */
    public const CREDIT_NOTES = [3, 8, 13];

    public const MONTHS = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // ------------------------------------------------------------------
    // Helpers SQL según el motor
    // ------------------------------------------------------------------

    public function isMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function hourExpr(string $column): string
    {
        return $this->isMysql() ? "HOUR({$column})" : "CAST(strftime('%H', {$column}) AS INTEGER)";
    }

    public function dateExpr(string $column): string
    {
        return "DATE({$column})";
    }

    public function secondsDiffExpr(string $start, string $end): string
    {
        return $this->isMysql()
            ? "TIMESTAMPDIFF(SECOND, {$start}, {$end})"
            : "((julianday({$end}) - julianday({$start})) * 86400)";
    }

    // ------------------------------------------------------------------
    // Consultas base con filtros
    // ------------------------------------------------------------------

    /** Producción válida filtrada. Alias: pr (production_records), c (crates), l (loads). */
    public function productionQuery(ReportFilters $f, bool $joinCrates = false): Builder
    {
        $q = DB::table('production_records as pr')
            ->whereNull('pr.voided_at')
            ->whereBetween('pr.recorded_at', [$f->from, $f->to]);

        if ($f->warehouseId) {
            $q->where('pr.warehouse_id', $f->warehouseId);
        }
        foreach (['variety_id', 'size_id', 'packer_id', 'shift_id', 'production_line_id'] as $key) {
            if ($id = $f->get($key)) {
                $q->where("pr.{$key}", $id);
            }
        }

        if ($joinCrates || $f->has('producer_id', 'lot_id', 'load_id', 'client_id', 'destination_id')) {
            $q->join('crates as c', 'c.id', '=', 'pr.crate_id');
            if ($id = $f->get('producer_id')) {
                $q->where('c.producer_id', $id);
            }
            if ($id = $f->get('lot_id')) {
                $q->where('c.lot_id', $id);
            }
            if ($id = $f->get('load_id')) {
                $q->where('c.current_load_id', $id);
            }
            if ($f->has('client_id', 'destination_id')) {
                $q->join('loads as l', 'l.id', '=', 'c.current_load_id');
                if ($id = $f->get('client_id')) {
                    $q->where('l.client_id', $id);
                }
                if ($id = $f->get('destination_id')) {
                    $q->where('l.destination_id', $id);
                }
            }
        }

        return $q;
    }

    /** Rechazos filtrados. Alias: r (rejects). */
    public function rejectsQuery(ReportFilters $f): Builder
    {
        $q = DB::table('rejects as r')->whereBetween('r.rejected_at', [$f->from, $f->to]);

        foreach (['variety_id', 'size_id', 'packer_id', 'lot_id', 'reason_id'] as $key) {
            if ($id = $f->get($key)) {
                $q->where("r.{$key}", $id);
            }
        }
        if ($id = $f->get('producer_id')) {
            $q->where(function ($w) use ($id) {
                $w->whereIn('r.lot_id', DB::table('lots')->select('id')->where('producer_id', $id))
                    ->orWhereIn('r.crate_id', DB::table('crates')->select('id')->where('producer_id', $id));
            });
        }

        return $q;
    }

    /** Cargas filtradas por fecha de carga. Alias: l (loads). */
    public function loadsQuery(ReportFilters $f): Builder
    {
        $q = DB::table('loads as l')
            ->whereNull('l.deleted_at')
            ->whereBetween('l.date', [$f->from->toDateString(), $f->to->toDateString()]);

        if ($f->warehouseId) {
            $q->where('l.warehouse_id', $f->warehouseId);
        }
        foreach (['client_id', 'destination_id'] as $key) {
            if ($id = $f->get($key)) {
                $q->where("l.{$key}", $id);
            }
        }
        if ($id = $f->get('load_id')) {
            $q->where('l.id', $id);
        }
        if ($f->status && LoadStatus::tryFrom($f->status)) {
            $q->where('l.status', $f->status);
        }

        return $q;
    }

    // ------------------------------------------------------------------
    // Totales
    // ------------------------------------------------------------------

    /** @return array{crates:int, kg:float, packers:int} */
    public function productionTotals(ReportFilters $f): array
    {
        $row = $this->productionQuery($f)
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(pr.weight), 0) as kg, COUNT(DISTINCT pr.packer_id) as packers')
            ->first();

        return ['crates' => (int) $row->crates, 'kg' => round((float) $row->kg, 2), 'packers' => (int) $row->packers];
    }

    /** Horas productivas: franjas (día, hora) con al menos un registro válido. */
    public function productiveHours(ReportFilters $f): int
    {
        $hour = $this->hourExpr('pr.recorded_at');
        $date = $this->dateExpr('pr.recorded_at');
        $sub = $this->productionQuery($f)->selectRaw("{$date} as d, {$hour} as h")->groupByRaw("{$date}, {$hour}");

        return (int) DB::query()->fromSub($sub, 't')->count();
    }

    /** @return array{count:int, kg:float} */
    public function rejectTotals(ReportFilters $f): array
    {
        $row = $this->rejectsQuery($f)->selectRaw('COUNT(*) as n, COALESCE(SUM(r.weight), 0) as kg')->first();

        return ['count' => (int) $row->n, 'kg' => round((float) $row->kg, 2)];
    }

    /**
     * Ingreso: cajones creados (no anulados) y pallets recibidos en el período.
     *
     * @return array{crates:int, crates_kg:float, pallets:int, pallets_kg:float}
     */
    public function intakeTotals(ReportFilters $f): array
    {
        $crates = DB::table('crates')
            ->whereNull('deleted_at')
            ->where('status', '!=', CrateStatus::Voided->value)
            ->whereBetween('created_at', [$f->from, $f->to])
            ->when($f->warehouseId, fn ($q) => $q->where('warehouse_id', $f->warehouseId))
            ->when($f->get('variety_id'), fn ($q, $id) => $q->where('variety_id', $id))
            ->when($f->get('producer_id'), fn ($q, $id) => $q->where('producer_id', $id))
            ->when($f->get('lot_id'), fn ($q, $id) => $q->where('lot_id', $id))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(weight), 0) as kg')
            ->first();

        $pallets = DB::table('pallets')
            ->whereNull('deleted_at')
            ->where('status', '!=', PalletStatus::Voided->value)
            ->whereBetween('received_at', [$f->from, $f->to])
            ->when($f->warehouseId, fn ($q) => $q->where('warehouse_id', $f->warehouseId))
            ->when($f->get('variety_id'), fn ($q, $id) => $q->where('variety_id', $id))
            ->when($f->get('producer_id'), fn ($q, $id) => $q->where('producer_id', $id))
            ->when($f->get('lot_id'), fn ($q, $id) => $q->where('lot_id', $id))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(gross_weight), 0) as kg')
            ->first();

        return [
            'crates' => (int) $crates->n,
            'crates_kg' => round((float) $crates->kg, 2),
            'pallets' => (int) $pallets->n,
            'pallets_kg' => round((float) $pallets->kg, 2),
        ];
    }

    /**
     * Despachos: cargas con dispatched_at en el período.
     *
     * @return array{loads:int, crates:int, kg:float, trucks:int, destinations:int}
     */
    public function dispatchTotals(ReportFilters $f): array
    {
        $row = DB::table('loads')
            ->whereNull('deleted_at')
            ->whereIn('status', [LoadStatus::Dispatched->value, LoadStatus::Delivered->value])
            ->whereBetween('dispatched_at', [$f->from, $f->to])
            ->when($f->warehouseId, fn ($q) => $q->where('warehouse_id', $f->warehouseId))
            ->when($f->get('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($f->get('destination_id'), fn ($q, $id) => $q->where('destination_id', $id))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_crates), 0) as crates, COALESCE(SUM(total_kg), 0) as kg,
                COUNT(DISTINCT truck_id) as trucks, COUNT(DISTINCT destination_id) as destinations')
            ->first();

        return [
            'loads' => (int) $row->n,
            'crates' => (int) $row->crates,
            'kg' => round((float) $row->kg, 2),
            'trucks' => (int) $row->trucks,
            'destinations' => (int) $row->destinations,
        ];
    }

    /** Minutos promedio entre creación y cierre de las cargas cerradas en el período. */
    public function averageLoadMinutes(ReportFilters $f): ?float
    {
        $diff = $this->secondsDiffExpr('created_at', 'closed_at');
        $avg = DB::table('loads')
            ->whereNull('deleted_at')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$f->from, $f->to])
            ->when($f->warehouseId, fn ($q) => $q->where('warehouse_id', $f->warehouseId))
            ->selectRaw("AVG({$diff}) as s")
            ->value('s');

        return $avg === null ? null : round((float) $avg / 60, 1);
    }

    /** Cargas pendientes (en armado o cerradas sin despachar), estado actual. */
    public function pendingLoads(?int $warehouseId = null): array
    {
        $rows = DB::table('loads')
            ->whereNull('deleted_at')
            ->whereIn('status', [LoadStatus::Draft->value, LoadStatus::Closed->value])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $draft = (int) ($rows[LoadStatus::Draft->value] ?? 0);
        $closed = (int) ($rows[LoadStatus::Closed->value] ?? 0);

        return ['draft' => $draft, 'closed' => $closed, 'total' => $draft + $closed];
    }

    /** Conteo actual de cajones por estado. */
    public function crateStatusCounts(?int $warehouseId = null): array
    {
        return DB::table('crates')
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Conteo actual de pallets por estado. */
    public function palletStatusCounts(?int $warehouseId = null): array
    {
        return DB::table('pallets')
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Indicadores del período (todos agregados en SQL).
     */
    public function indicators(ReportFilters $f): array
    {
        $prod = $this->productionTotals($f);
        $rej = $this->rejectTotals($f);
        $intake = $this->intakeTotals($f);
        $dispatch = $this->dispatchTotals($f);
        $hours = $this->productiveHours($f);

        $base = $prod['kg'] + $rej['kg'];
        $wastePct = $base > 0 ? round($rej['kg'] / $base * 100, 2) : 0.0;

        return [
            'crates_in' => $intake['crates'],
            'kg_in' => $intake['pallets_kg'] > 0 ? $intake['pallets_kg'] : $intake['crates_kg'],
            'pallets_in' => $intake['pallets'],
            'crates_processed' => $prod['crates'],
            'kg_processed' => $prod['kg'],
            'packers' => $prod['packers'],
            'kg_rejected' => $rej['kg'],
            'rejects' => $rej['count'],
            'kg_dispatched' => $dispatch['kg'],
            'loads_dispatched' => $dispatch['loads'],
            'trucks_dispatched' => $dispatch['trucks'],
            'destinations' => $dispatch['destinations'],
            'waste_pct' => $wastePct,
            'yield_pct' => $base > 0 ? round(100 - $wastePct, 2) : 0.0,
            'productive_hours' => $hours,
            'crates_per_hour' => $hours > 0 ? round($prod['crates'] / $hours, 1) : 0.0,
            'kg_per_hour' => $hours > 0 ? round($prod['kg'] / $hours, 1) : 0.0,
            'avg_kg_per_crate' => $prod['crates'] > 0 ? round($prod['kg'] / $prod['crates'], 2) : 0.0,
            'crates_per_packer' => $prod['packers'] > 0 ? round($prod['crates'] / $prod['packers'], 1) : 0.0,
            'kg_per_packer' => $prod['packers'] > 0 ? round($prod['kg'] / $prod['packers'], 1) : 0.0,
            'avg_load_minutes' => $this->averageLoadMinutes($f),
            'pending_loads' => $this->pendingLoads($f->warehouseId)['total'],
        ];
    }

    // ------------------------------------------------------------------
    // Agrupaciones
    // ------------------------------------------------------------------

    /**
     * Producción agrupada por día/semana/mes/año (a partir de agregados diarios).
     *
     * @return list<array{key:string, label:string, crates:int, kg:float}>
     */
    public function productionByPeriod(ReportFilters $f, ?string $group = null, bool $fillGaps = true): array
    {
        $group ??= $f->group;
        $date = $this->dateExpr('pr.recorded_at');
        $daily = $this->productionQuery($f)
            ->selectRaw("{$date} as day, COUNT(*) as crates, COALESCE(SUM(pr.weight), 0) as kg")
            ->groupByRaw($date)
            ->orderBy('day')
            ->get();

        $buckets = [];
        if ($fillGaps && $group === 'day' && $f->days() <= 93) {
            for ($d = $f->from->startOfDay(); $d->lessThanOrEqualTo($f->to); $d = $d->addDay()) {
                [$key, $label] = $this->bucket($d, 'day');
                $buckets[$key] = ['key' => $key, 'label' => $label, 'crates' => 0, 'kg' => 0.0];
            }
        }

        foreach ($daily as $row) {
            [$key, $label] = $this->bucket(CarbonImmutable::parse($row->day), $group);
            $buckets[$key] ??= ['key' => $key, 'label' => $label, 'crates' => 0, 'kg' => 0.0];
            $buckets[$key]['crates'] += (int) $row->crates;
            $buckets[$key]['kg'] = round($buckets[$key]['kg'] + (float) $row->kg, 2);
        }
        ksort($buckets);

        return array_values($buckets);
    }

    /** @return array{0:string, 1:string} clave ordenable y etiqueta */
    public function bucket(CarbonInterface $day, string $group): array
    {
        return match ($group) {
            'week' => [$day->copy()->startOfWeek()->format('Y-m-d'), 'Sem. '.$day->copy()->startOfWeek()->format('d/m/Y')],
            'month' => [$day->format('Y-m'), self::MONTHS[(int) $day->format('n')].' '.$day->format('Y')],
            'year' => [$day->format('Y'), $day->format('Y')],
            default => [$day->format('Y-m-d'), $day->format('d/m')],
        };
    }

    /**
     * Producción por hora del día (0..23).
     *
     * @return list<array{hour:int, crates:int, kg:float}>
     */
    public function productionByHour(ReportFilters $f): array
    {
        $hour = $this->hourExpr('pr.recorded_at');
        $rows = $this->productionQuery($f)
            ->selectRaw("{$hour} as h, COUNT(*) as crates, COALESCE(SUM(pr.weight), 0) as kg")
            ->groupByRaw($hour)
            ->get()
            ->keyBy(fn ($r) => (int) $r->h);

        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = [
                'hour' => $h,
                'crates' => (int) ($rows[$h]->crates ?? 0),
                'kg' => round((float) ($rows[$h]->kg ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Producción agrupada por una dimensión.
     *
     * @param  string  $dimension  packer|variety|size|producer|lot|shift|line|owner
     * @return list<array{id:int|null, code:string|null, label:string, color:string|null, crates:int, kg:float, avg_kg:float, share:float}>
     */
    public function productionBy(string $dimension, ReportFilters $f, ?int $limit = null): array
    {
        $map = [
            'packer' => ['pr.packer_id', 'packers', 'p', ['p.code as code', "p.last_name || ', ' || p.first_name as label"], ['p.code', 'p.last_name', 'p.first_name'], 'kg'],
            'variety' => ['pr.variety_id', 'varieties', 'v', ['v.code as code', 'v.name as label', 'v.color as color'], ['v.code', 'v.name', 'v.color'], 'kg'],
            'size' => ['pr.size_id', 'sizes', 's', ['s.code as code', 's.name as label', 's.sort as sort'], ['s.code', 's.name', 's.sort'], 'sort'],
            'producer' => ['c.producer_id', 'producers', 'pd', ['pd.code as code', 'pd.name as label'], ['pd.code', 'pd.name'], 'kg'],
            'owner' => ['c.owner_id', 'owners', 'ow', ['ow.code as code', 'ow.name as label'], ['ow.code', 'ow.name'], 'kg'],
            'lot' => ['c.lot_id', 'lots', 'lt', ['lt.code as code', 'lt.code as label'], ['lt.code'], 'kg'],
            'shift' => ['pr.shift_id', 'shifts', 'sh', ['sh.code as code', 'sh.name as label'], ['sh.code', 'sh.name'], 'kg'],
            'line' => ['pr.production_line_id', 'production_lines', 'pl', ['pl.code as code', 'pl.name as label'], ['pl.code', 'pl.name'], 'kg'],
        ];
        abort_unless(isset($map[$dimension]), 500, 'Dimensión de reporte desconocida.');
        [$column, $table, $alias, $selects, $groups, $order] = $map[$dimension];

        $q = $this->productionQuery($f, str_starts_with($column, 'c.'))
            ->leftJoin("{$table} as {$alias}", "{$alias}.id", '=', $column);

        // Concatenación portable para el nombre del embalador.
        $selects = array_map(fn ($s) => $this->isMysql() ? str_replace("p.last_name || ', ' || p.first_name", "CONCAT(p.last_name, ', ', p.first_name)", $s) : $s, $selects);

        $q->selectRaw("{$column} as id, COUNT(*) as crates, COALESCE(SUM(pr.weight), 0) as kg")
            ->addSelect(array_map(fn ($s) => DB::raw($s), $selects))
            ->groupBy(array_merge([$column], $groups));

        $order === 'sort' ? $q->orderBy("{$alias}.sort")->orderBy("{$alias}.name") : $q->orderByDesc('kg');
        if ($limit) {
            $q->limit($limit);
        }

        $rows = $q->get();
        $totalKg = $limit ? $this->productionTotals($f)['kg'] : (float) $rows->sum('kg');

        return $rows->map(fn ($r) => [
            'id' => $r->id !== null ? (int) $r->id : null,
            'code' => $r->code ?? null,
            'label' => $r->id === null ? 'Sin asignar' : (string) ($r->label ?? $r->code ?? '#'.$r->id),
            'color' => $r->color ?? null,
            'crates' => (int) $r->crates,
            'kg' => round((float) $r->kg, 2),
            'avg_kg' => $r->crates > 0 ? round((float) $r->kg / (int) $r->crates, 2) : 0.0,
            'share' => $totalKg > 0 ? round((float) $r->kg / $totalKg * 100, 2) : 0.0,
        ])->values()->all();
    }

    /**
     * Horas productivas por embalador (franjas día+hora con registros).
     *
     * @return array<int, int> packer_id => horas
     */
    public function productiveHoursByPacker(ReportFilters $f): array
    {
        $hour = $this->hourExpr('pr.recorded_at');
        $date = $this->dateExpr('pr.recorded_at');
        $sub = $this->productionQuery($f)
            ->selectRaw("pr.packer_id as packer_id, {$date} as d, {$hour} as h")
            ->groupByRaw("pr.packer_id, {$date}, {$hour}");

        return DB::query()->fromSub($sub, 't')
            ->selectRaw('packer_id, COUNT(*) as hours')
            ->groupBy('packer_id')
            ->pluck('hours', 'packer_id')
            ->map(fn ($h) => (int) $h)
            ->all();
    }

    /**
     * Rechazos agrupados.
     *
     * @param  string  $dimension  reason|variety|size|packer|lot|day
     * @return list<array{id:int|null, label:string, count:int, kg:float, share:float}>
     */
    public function rejectsBy(string $dimension, ReportFilters $f, ?int $limit = null): array
    {
        $q = $this->rejectsQuery($f);
        if ($dimension === 'day') {
            $date = $this->dateExpr('r.rejected_at');
            $rows = $q->selectRaw("{$date} as id, {$date} as label, COUNT(*) as n, COALESCE(SUM(r.weight), 0) as kg")
                ->groupByRaw($date)->orderBy('id')->get();
        } else {
            $map = [
                'reason' => ['r.reason_id', 'reasons', 'rs', 'rs.name'],
                'variety' => ['r.variety_id', 'varieties', 'v', 'v.name'],
                'size' => ['r.size_id', 'sizes', 's', 's.name'],
                'packer' => ['r.packer_id', 'packers', 'p', $this->isMysql() ? "CONCAT(p.last_name, ', ', p.first_name)" : "p.last_name || ', ' || p.first_name"],
                'lot' => ['r.lot_id', 'lots', 'lt', 'lt.code'],
            ];
            abort_unless(isset($map[$dimension]), 500, 'Dimensión de reporte desconocida.');
            [$column, $table, $alias, $label] = $map[$dimension];
            $groupCols = $dimension === 'packer' ? [$column, 'p.last_name', 'p.first_name'] : [$column, DB::raw($label)];
            $q->leftJoin("{$table} as {$alias}", "{$alias}.id", '=', $column)
                ->selectRaw("{$column} as id, {$label} as label, COUNT(*) as n, COALESCE(SUM(r.weight), 0) as kg")
                ->groupBy($groupCols)
                ->orderByDesc('kg');
            if ($limit) {
                $q->limit($limit);
            }
            $rows = $q->get();
        }

        $total = (float) $rows->sum('kg');

        return $rows->map(fn ($r) => [
            'id' => $r->id,
            'label' => $dimension === 'day' ? CarbonImmutable::parse($r->label)->format('d/m') : ($r->id === null ? 'Sin asignar' : (string) $r->label),
            'count' => (int) $r->n,
            'kg' => round((float) $r->kg, 2),
            'share' => $total > 0 ? round((float) $r->kg / $total * 100, 2) : 0.0,
        ])->values()->all();
    }

    /**
     * Rechazos (kg) por embalador: para calcular la merma individual.
     *
     * @return array<int, float>
     */
    public function rejectedKgByPacker(ReportFilters $f): array
    {
        return $this->rejectsQuery($f)
            ->whereNotNull('r.packer_id')
            ->selectRaw('r.packer_id, COALESCE(SUM(r.weight), 0) as kg')
            ->groupBy('r.packer_id')
            ->pluck('kg', 'packer_id')
            ->map(fn ($kg) => round((float) $kg, 2))
            ->all();
    }

    /** Cargas agrupadas por destino. */
    public function loadsByDestination(ReportFilters $f): array
    {
        return $this->loadsQuery($f)
            ->where('l.status', '!=', LoadStatus::Cancelled->value)
            ->leftJoin('destinations as d', 'd.id', '=', 'l.destination_id')
            ->selectRaw('l.destination_id as id, d.name as label, COUNT(*) as loads, COALESCE(SUM(l.total_crates), 0) as crates,
                COALESCE(SUM(l.total_kg), 0) as kg, COUNT(DISTINCT l.truck_id) as trucks')
            ->groupBy('l.destination_id', 'd.name')
            ->orderByDesc('kg')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label ?? 'Sin destino',
                'loads' => (int) $r->loads,
                'crates' => (int) $r->crates,
                'kg' => round((float) $r->kg, 2),
                'trucks' => (int) $r->trucks,
            ])->all();
    }

    /** Cargas por estado (en el período). */
    public function loadsByStatus(ReportFilters $f): array
    {
        return $this->loadsQuery($f)
            ->selectRaw('l.status, COUNT(*) as n, COALESCE(SUM(l.total_kg), 0) as kg')
            ->groupBy('l.status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'label' => LoadStatus::tryFrom($r->status)?->label() ?? $r->status,
                'loads' => (int) $r->n,
                'kg' => round((float) $r->kg, 2),
            ])->all();
    }

    /** Cargas despachadas por día (dispatched_at). */
    public function dispatchesByDay(ReportFilters $f): array
    {
        $date = $this->dateExpr('dispatched_at');
        $rows = DB::table('loads')
            ->whereNull('deleted_at')
            ->whereNotNull('dispatched_at')
            ->whereBetween('dispatched_at', [$f->from, $f->to])
            ->when($f->warehouseId, fn ($q) => $q->where('warehouse_id', $f->warehouseId))
            ->selectRaw("{$date} as day, COUNT(*) as loads, COALESCE(SUM(total_kg), 0) as kg")
            ->groupByRaw($date)
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $out = [];
        if ($f->days() <= 93) {
            for ($d = $f->from->startOfDay(); $d->lessThanOrEqualTo($f->to); $d = $d->addDay()) {
                $r = $rows[$d->toDateString()] ?? null;
                $out[] = ['label' => $d->format('d/m'), 'loads' => (int) ($r->loads ?? 0), 'kg' => round((float) ($r->kg ?? 0), 2)];
            }

            return $out;
        }

        return $rows->map(fn ($r) => ['label' => CarbonImmutable::parse($r->day)->format('d/m/Y'), 'loads' => (int) $r->loads, 'kg' => round((float) $r->kg, 2)])->values()->all();
    }

    // ------------------------------------------------------------------
    // Objetivos
    // ------------------------------------------------------------------

    /**
     * Objetivo de producción (kg) para daily|weekly|monthly.
     * Prioridad: objetivo general (sin línea ni turno) → suma de objetivos por línea/turno → configuración.
     */
    public function targetKg(string $period): float
    {
        $targets = ProductionTarget::query()->where('period', $period)->get(['production_line_id', 'shift_id', 'target_kg']);
        $general = $targets->first(fn ($t) => $t->production_line_id === null && $t->shift_id === null);
        if ($general && (float) $general->target_kg > 0) {
            return (float) $general->target_kg;
        }
        $sum = (float) $targets->sum('target_kg');
        if ($sum > 0) {
            return $sum;
        }

        return (float) setting("production.target_{$period}_kg", 0);
    }

    // ------------------------------------------------------------------
    // Comparaciones históricas
    // ------------------------------------------------------------------

    /** Diferencia absoluta y porcentual. */
    public function compare(float $current, float $previous): array
    {
        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'diff' => round($current - $previous, 2),
            'pct' => $previous != 0.0 ? round(($current - $previous) / abs($previous) * 100, 1) : null,
        ];
    }

    /**
     * Rangos de comparación "a la fecha": hoy vs ayer, semana vs anterior (mismos días),
     * mes vs anterior (mismos días), temporada vs anterior (mismo avance).
     *
     * @return array<string, array{label:string, current:array{0:CarbonImmutable,1:CarbonImmutable}, previous:array{0:CarbonImmutable,1:CarbonImmutable}|null}>
     */
    public function comparisonRanges(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();
        $end = $now->endOfDay();

        $weekStart = $today->startOfWeek();
        $monthStart = $today->startOfMonth();
        $prevMonthStart = $monthStart->subMonthNoOverflow();
        $prevMonthEnd = $prevMonthStart->addDays($today->day - 1)->endOfDay();
        if ($prevMonthEnd->greaterThan($prevMonthStart->endOfMonth())) {
            $prevMonthEnd = $prevMonthStart->endOfMonth();
        }

        $ranges = [
            'day' => ['label' => 'Hoy vs ayer', 'current' => [$today, $end], 'previous' => [$today->subDay(), $end->subDay()]],
            'week' => ['label' => 'Semana actual vs anterior', 'current' => [$weekStart, $end], 'previous' => [$weekStart->subWeek(), $end->subWeek()]],
            'month' => ['label' => 'Mes actual vs anterior', 'current' => [$monthStart, $end], 'previous' => [$prevMonthStart, $prevMonthEnd]],
        ];

        $season = Season::current();
        if ($season) {
            $start = CarbonImmutable::parse($season->starts_on->toDateString());
            $prev = Season::query()->where('starts_on', '<', $season->starts_on)->orderByDesc('starts_on')->first();
            $previous = null;
            if ($prev) {
                $pStart = CarbonImmutable::parse($prev->starts_on->toDateString());
                $pEnd = $pStart->addDays((int) $start->diffInDays($today))->endOfDay();
                $pLimit = CarbonImmutable::parse($prev->ends_on->toDateString())->endOfDay();
                $previous = [$pStart, $pEnd->greaterThan($pLimit) ? $pLimit : $pEnd];
            }
            $ranges['season'] = ['label' => 'Temporada actual vs anterior ('.$season->name.')', 'current' => [$start, $end], 'previous' => $previous];
        }

        return $ranges;
    }

    /**
     * Comparaciones con diferencias absolutas y porcentuales.
     *
     * @param  list<string>|null  $only  claves de comparisonRanges() a calcular
     */
    public function comparisons(?ReportFilters $base = null, ?array $only = null, bool $full = true): array
    {
        $base ??= ReportFilters::forDay(CarbonImmutable::today());
        $out = [];
        foreach ($this->comparisonRanges() as $key => $range) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }
            $cur = $this->comparisonMetrics($base->withRange(...$range['current']), $full);
            $prev = $range['previous'] ? $this->comparisonMetrics($base->withRange(...$range['previous']), $full) : array_map(fn () => 0.0, $cur);

            $metrics = [];
            foreach ($cur as $metric => $value) {
                $metrics[$metric] = $this->compare($value, $prev[$metric] ?? 0.0);
            }
            $out[$key] = [
                'label' => $range['label'],
                'current_range' => $range['current'][0]->format('d/m/Y').' – '.$range['current'][1]->format('d/m/Y'),
                'previous_range' => $range['previous'] ? $range['previous'][0]->format('d/m/Y').' – '.$range['previous'][1]->format('d/m/Y') : 'Sin datos',
                'metrics' => $metrics,
            ];
        }

        return $out;
    }

    /** @return array<string, float> */
    private function comparisonMetrics(ReportFilters $f, bool $full): array
    {
        $prod = $this->productionTotals($f);
        $metrics = ['kg' => $prod['kg'], 'crates' => (float) $prod['crates']];
        if (! $full) {
            return $metrics;
        }
        $rej = $this->rejectTotals($f);
        $dispatch = $this->dispatchTotals($f);
        $base = $prod['kg'] + $rej['kg'];

        return $metrics + [
            'rejected_kg' => $rej['kg'],
            'waste_pct' => $base > 0 ? round($rej['kg'] / $base * 100, 2) : 0.0,
            'dispatched_kg' => $dispatch['kg'],
            'loads' => (float) $dispatch['loads'],
        ];
    }

    public const COMPARISON_METRICS = [
        'kg' => ['Kg procesados', 'kg'],
        'crates' => ['Cajones procesados', 'int'],
        'rejected_kg' => ['Kg rechazados', 'kg'],
        'waste_pct' => ['% merma', 'pct'],
        'dispatched_kg' => ['Kg despachados', 'kg'],
        'loads' => ['Cargas despachadas', 'int'],
    ];

    // ------------------------------------------------------------------
    // Finanzas (sólo para usuarios con costs.view / billing.view / profit.view)
    // ------------------------------------------------------------------

    /** Facturación autorizada del período (neto y total, en moneda local; notas de crédito restan). */
    public function billingTotals(ReportFilters $f): array
    {
        $credit = implode(',', self::CREDIT_NOTES);
        $row = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('status', InvoiceStatus::Authorized->value)
            ->whereBetween('issued_on', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($f->get('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->selectRaw("COUNT(*) as n,
                COALESCE(SUM(CASE WHEN voucher_type IN ({$credit}) THEN -net_amount * exchange_rate ELSE net_amount * exchange_rate END), 0) as net,
                COALESCE(SUM(CASE WHEN voucher_type IN ({$credit}) THEN -total_amount * exchange_rate ELSE total_amount * exchange_rate END), 0) as total")
            ->first();

        $pending = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Pending->value, InvoiceStatus::Rejected->value])
            ->whereBetween('issued_on', [$f->from->toDateString(), $f->to->toDateString()])
            ->count();

        return ['count' => (int) $row->n, 'net' => round((float) $row->net, 2), 'total' => round((float) $row->total, 2), 'pending' => $pending];
    }

    /** Costos del período por categoría y moneda. */
    public function costTotals(ReportFilters $f): array
    {
        $main = (string) setting('regional.currency', 'ARS');
        $rows = DB::table('costs')
            ->whereBetween('date', [$f->from->toDateString(), $f->to->toDateString()])
            ->selectRaw('category, currency, COALESCE(SUM(amount), 0) as total, COUNT(*) as n')
            ->groupBy('category', 'currency')
            ->get();

        $byCategory = [];
        foreach (Cost::CATEGORIES as $key => $label) {
            $byCategory[$key] = ['key' => $key, 'label' => $label, 'total' => 0.0, 'count' => 0];
        }
        $other = [];
        $total = 0.0;
        foreach ($rows as $r) {
            if ($r->currency !== $main) {
                $other[$r->currency] = round(($other[$r->currency] ?? 0) + (float) $r->total, 2);
                continue;
            }
            $byCategory[$r->category] ??= ['key' => $r->category, 'label' => $r->category, 'total' => 0.0, 'count' => 0];
            $byCategory[$r->category]['total'] = round($byCategory[$r->category]['total'] + (float) $r->total, 2);
            $byCategory[$r->category]['count'] += (int) $r->n;
            $total += (float) $r->total;
        }

        return ['currency' => $main, 'total' => round($total, 2), 'by_category' => array_values($byCategory), 'other_currencies' => $other];
    }

    /** Rentabilidad: ingresos (facturas autorizadas, neto) − costos. */
    public function profitability(ReportFilters $f): array
    {
        $billing = $this->billingTotals($f);
        $costs = $this->costTotals($f);
        $kg = $this->productionTotals($f)['kg'];
        $margin = $billing['net'] - $costs['total'];

        return [
            'revenue' => $billing['net'],
            'revenue_total' => $billing['total'],
            'costs' => $costs['total'],
            'margin' => round($margin, 2),
            'margin_pct' => $billing['net'] > 0 ? round($margin / $billing['net'] * 100, 2) : null,
            'cost_per_kg' => $kg > 0 ? round($costs['total'] / $kg, 2) : null,
            'revenue_per_kg' => $kg > 0 ? round($billing['net'] / $kg, 2) : null,
            'currency' => $costs['currency'],
            'other_currencies' => $costs['other_currencies'],
        ];
    }
}
