<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Pallet;
use App\Models\ProductionRecord;
use App\Models\Reason;
use App\Models\Reject;
use App\Models\StateHistory;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Merma y rendimiento. Métodos reutilizables por reportes y dashboard.
 *
 * Definiciones (período [from, to] inclusive):
 * - kg rechazados: suma de rejects.weight por rejected_at.
 * - kg procesados: suma de production_records.weight válidos (no anulados) por recorded_at.
 * - % merma: kg rechazados / kg procesados × 100.
 * - kg ingresados: pallets recibidos (received_at) — gross_weight o, si falta, suma de sus cajones.
 * - kg despachados: cajones en estado despachado/facturado cuya transición a «despachado» ocurrió en el período.
 * - % aprovechamiento: (kg procesados − kg rechazados) / kg ingresados × 100 (null si no hubo ingresos).
 *
 * Filtros admitidos: variety_id, size_id, lot_id, packer_id, reason_id (sólo aplica a rechazos).
 */
class WasteService
{
    /**
     * @return array{rejected_kg: float, rejected_count: int, processed_kg: float, waste_pct: float,
     *               received_kg: float, dispatched_kg: float, utilization_pct: float|null}
     */
    public function summary(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rejects = $this->rejectsQuery($from, $to, $filters);
        $rejectedKg = (float) (clone $rejects)->sum('weight');
        $rejectedCount = (int) (clone $rejects)->count();
        $processedKg = $this->processedKg($from, $to, $filters);
        $receivedKg = $this->receivedKg($from, $to, $filters);

        return [
            'rejected_kg' => round($rejectedKg, 2),
            'rejected_count' => $rejectedCount,
            'processed_kg' => round($processedKg, 2),
            'waste_pct' => $this->pct($rejectedKg, $processedKg),
            'received_kg' => round($receivedKg, 2),
            'dispatched_kg' => round($this->dispatchedKg($from, $to, $filters), 2),
            'utilization_pct' => $receivedKg > 0 ? round(($processedKg - $rejectedKg) / $receivedKg * 100, 2) : null,
        ];
    }

    /** @return list<array{id: int|null, label: string, kg: float, count: int, processed_kg: float, pct: float}> */
    public function byVariety(Carbon $from, Carbon $to, array $filters = []): array
    {
        $processed = $this->processedQuery($from, $to, $filters)
            ->selectRaw('variety_id as gid, SUM(weight) as kg')->groupBy('variety_id')->pluck('kg', 'gid');
        $names = Variety::query()->pluck('name', 'id');

        return $this->grouped('variety_id', $from, $to, $filters, fn ($id) => $names[$id] ?? 'Sin variedad', $processed->all());
    }

    /** @return list<array{id: int|null, label: string, kg: float, count: int, processed_kg: float, pct: float}> */
    public function byLot(Carbon $from, Carbon $to, array $filters = []): array
    {
        $processed = $this->processedQuery($from, $to, $filters)
            ->join('crates', 'crates.id', '=', 'production_records.crate_id')
            ->selectRaw('crates.lot_id as gid, SUM(production_records.weight) as kg')
            ->groupBy('crates.lot_id')->pluck('kg', 'gid');
        $codes = Lot::query()->withTrashed()->pluck('code', 'id');

        return $this->grouped('lot_id', $from, $to, $filters, fn ($id) => $codes[$id] ?? 'Sin lote', $processed->all());
    }

    /** @return list<array{id: int|null, label: string, kg: float, count: int, processed_kg: float, pct: float}> */
    public function byPacker(Carbon $from, Carbon $to, array $filters = []): array
    {
        $processed = $this->processedQuery($from, $to, $filters)
            ->selectRaw('packer_id as gid, SUM(weight) as kg')->groupBy('packer_id')->pluck('kg', 'gid');
        $names = Packer::query()->withTrashed()->get(['id', 'code', 'first_name', 'last_name'])
            ->mapWithKeys(fn (Packer $p) => [$p->id => $p->code.' — '.$p->full_name]);

        return $this->grouped('packer_id', $from, $to, $filters, fn ($id) => $names[$id] ?? 'Sin embalador', $processed->all());
    }

    /**
     * Rechazos por motivo. `pct` = participación sobre el total de kg rechazados.
     *
     * @return list<array{id: int|null, label: string, kg: float, count: int, pct: float}>
     */
    public function byReason(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rows = $this->rejectsQuery($from, $to, $filters)
            ->selectRaw('reason_id as gid, SUM(weight) as kg, COUNT(*) as cnt')
            ->groupBy('reason_id')->get();
        $total = (float) $rows->sum('kg');
        $names = Reason::query()->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'id' => $r->gid,
            'label' => $names[$r->gid] ?? 'Sin motivo',
            'kg' => round((float) $r->kg, 2),
            'count' => (int) $r->cnt,
            'pct' => $this->pct((float) $r->kg, $total),
        ])->sortByDesc('kg')->values()->all();
    }

    /** Consulta base de rechazos del período con filtros (útil para listados/exportaciones). */
    public function rejectsQuery(Carbon $from, Carbon $to, array $filters = []): Builder
    {
        return Reject::query()
            ->whereBetween('rejected_at', [$from, $to])
            ->when($filters['variety_id'] ?? null, fn ($q, $v) => $q->where('variety_id', $v))
            ->when($filters['size_id'] ?? null, fn ($q, $v) => $q->where('size_id', $v))
            ->when($filters['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v))
            ->when($filters['packer_id'] ?? null, fn ($q, $v) => $q->where('packer_id', $v))
            ->when($filters['reason_id'] ?? null, fn ($q, $v) => $q->where('reason_id', $v));
    }

    public function processedKg(Carbon $from, Carbon $to, array $filters = []): float
    {
        return (float) $this->processedQuery($from, $to, $filters)->sum('production_records.weight');
    }

    public function receivedKg(Carbon $from, Carbon $to, array $filters = []): float
    {
        if (! empty($filters['size_id']) || ! empty($filters['packer_id'])) {
            // Los pallets no tienen tamaño ni embalador: se usa la suma de cajones de pallets recibidos en el período.
            return (float) Crate::query()
                ->whereIn('pallet_id', Pallet::query()->select('id')->whereBetween('received_at', [$from, $to]))
                ->tap(fn ($q) => $this->crateFilters($q, $filters))
                ->sum('weight');
        }

        $pallets = Pallet::query()
            ->whereBetween('received_at', [$from, $to])
            ->where('status', '!=', 'voided')
            ->when($filters['variety_id'] ?? null, fn ($q, $v) => $q->where('variety_id', $v))
            ->when($filters['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v));

        $gross = (float) (clone $pallets)->whereNotNull('gross_weight')->sum('gross_weight');
        // Pallets sin peso bruto: se toma la suma de sus cajones.
        $fromCrates = (float) Crate::query()
            ->whereIn('pallet_id', (clone $pallets)->whereNull('gross_weight')->select('id'))
            ->sum('weight');

        return $gross + $fromCrates;
    }

    public function dispatchedKg(Carbon $from, Carbon $to, array $filters = []): float
    {
        $dispatchedIds = StateHistory::query()
            ->select('stateful_id')
            ->where('stateful_type', (new Crate)->getMorphClass())
            ->where('to_state', CrateStatus::Dispatched->value)
            ->whereBetween('created_at', [$from, $to]);

        return (float) Crate::query()
            ->whereIn('status', [CrateStatus::Dispatched->value, CrateStatus::Invoiced->value])
            ->whereIn('id', $dispatchedIds)
            ->tap(fn ($q) => $this->crateFilters($q, $filters))
            ->sum('weight');
    }

    private function processedQuery(Carbon $from, Carbon $to, array $filters): Builder
    {
        return ProductionRecord::query()
            ->whereNull('production_records.voided_at')
            ->whereBetween('production_records.recorded_at', [$from, $to])
            ->when($filters['variety_id'] ?? null, fn ($q, $v) => $q->where('production_records.variety_id', $v))
            ->when($filters['size_id'] ?? null, fn ($q, $v) => $q->where('production_records.size_id', $v))
            ->when($filters['packer_id'] ?? null, fn ($q, $v) => $q->where('production_records.packer_id', $v))
            ->when($filters['lot_id'] ?? null, fn ($q, $v) => $q->whereIn(
                'production_records.crate_id',
                Crate::query()->withTrashed()->select('id')->where('lot_id', $v)
            ));
    }

    private function crateFilters(Builder $query, array $filters): void
    {
        foreach (['variety_id', 'size_id', 'lot_id', 'packer_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where('crates.'.$key, $filters[$key]);
            }
        }
    }

    /**
     * Agrupa rechazos por una columna y cruza con los kg procesados del mismo grupo.
     *
     * @param  array<int|string, float|string>  $processed
     */
    private function grouped(string $column, Carbon $from, Carbon $to, array $filters, callable $label, array $processed): array
    {
        $rows = $this->rejectsQuery($from, $to, $filters)
            ->selectRaw("{$column} as gid, SUM(weight) as kg, COUNT(*) as cnt")
            ->groupBy($column)->get();

        return $rows->map(function ($r) use ($label, $processed) {
            $kg = (float) $r->kg;
            $proc = (float) ($processed[$r->gid ?? ''] ?? 0);

            return [
                'id' => $r->gid,
                'label' => $label($r->gid),
                'kg' => round($kg, 2),
                'count' => (int) $r->cnt,
                'processed_kg' => round($proc, 2),
                'pct' => $this->pct($kg, $proc),
            ];
        })->sortByDesc('kg')->values()->all();
    }

    private function pct(float $part, float $total): float
    {
        return $total > 0 ? round($part / $total * 100, 2) : 0.0;
    }
}
