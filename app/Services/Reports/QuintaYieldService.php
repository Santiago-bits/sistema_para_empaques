<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rendimiento por quinta (productor): lo que entró (bines y kilos de los lotes) contra lo que salió empacado
 * (bultos y kilos registrados en producción) y lo descartado, en un período. Antes: hoja «RENDIMIENTO QUINTA».
 */
class QuintaYieldService
{
    /** @return Collection<int, object> una fila por productor */
    public function rows(string $from, string $to, ?int $producerId = null): Collection
    {
        $lots = DB::table('lots')
            ->leftJoin('producers', 'producers.id', '=', 'lots.producer_id')
            ->whereNull('lots.deleted_at')->where('lots.status', '!=', 'voided')
            ->whereDate('lots.date', '>=', $from)->whereDate('lots.date', '<=', $to)
            ->when($producerId, fn ($q) => $q->where('lots.producer_id', $producerId))
            ->get(['lots.id', 'lots.producer_id', 'producers.name as producer', 'lots.bins', 'lots.kg_received']);
        if ($lots->isEmpty()) {
            return collect();
        }
        $ids = $lots->pluck('id')->all();

        $packed = DB::table('production_records')->join('crates', 'crates.id', '=', 'production_records.crate_id')
            ->whereNull('production_records.voided_at')->whereIn('crates.lot_id', $ids)
            ->groupBy('crates.lot_id')->selectRaw('crates.lot_id, COUNT(*) as packages, COALESCE(SUM(production_records.weight), 0) as kg')
            ->get()->keyBy('lot_id');
        $rejected = DB::table('rejects')->whereIn('lot_id', $ids)
            ->groupBy('lot_id')->selectRaw('lot_id, COALESCE(SUM(weight), 0) as kg')->get()->keyBy('lot_id');

        return $lots->groupBy(fn ($l) => $l->producer_id ?? 0)->map(function (Collection $group) use ($packed, $rejected) {
            $received = (float) $group->sum('kg_received');
            $packedKg = (float) $group->sum(fn ($l) => (float) ($packed[$l->id]->kg ?? 0));
            $rejectedKg = (float) $group->sum(fn ($l) => (float) ($rejected[$l->id]->kg ?? 0));

            return (object) [
                'producer_id' => $group->first()->producer_id,
                'producer' => $group->first()->producer ?? 'Sin productor',
                'lots' => $group->count(),
                'bins' => (int) $group->sum('bins'),
                'kg_received' => $received,
                'packages' => (int) $group->sum(fn ($l) => (int) ($packed[$l->id]->packages ?? 0)),
                'kg_packed' => round($packedKg, 2),
                'kg_rejected' => round($rejectedKg, 2),
                'yield_pct' => $received > 0 ? round($packedKg / $received * 100, 1) : null,
                'waste_pct' => $received > 0 ? round($rejectedKg / $received * 100, 1) : null,
            ];
        })->sortBy('producer')->values();
    }
}
