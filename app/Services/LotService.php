<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Pallet;
use App\Models\ProductionRecord;
use App\Models\Reject;
use App\Models\Season;
use App\Models\StateHistory;
use Illuminate\Support\Facades\DB;

/**
 * Lotes de ingreso. El estado es un string (open | closed | voided): los cambios se
 * aplican con UPDATE condicionado al estado actual, guardan historial y se auditan.
 */
class LotService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AuditService $audit,
        private readonly StateTransitionService $states,
    ) {
    }

    /** Código que se asignará si el usuario no ingresa uno propio (no lo consume). */
    public function nextCode(): string
    {
        return $this->sequences->peek('lot');
    }

    public function create(array $data): Lot
    {
        return DB::transaction(function () use ($data) {
            $code = $data['code'] ?? null;
            if (blank($code)) {
                // Si alguien cargó a mano un código con el formato de la numeración, se saltea.
                do {
                    $code = $this->sequences->next('lot');
                } while (Lot::withTrashed()->where('code', $code)->exists());
            }

            $lot = Lot::query()->create(array_merge($data, [
                'code' => $code,
                'date' => $data['date'] ?? today(),
                'season_id' => $data['season_id'] ?? Season::current()?->id,
                'quantity' => $data['quantity'] ?? 0,
                'status' => 'open',
                'created_by' => auth()->id(),
            ]));

            $this->states->recordInitial($lot, 'open');

            return $lot;
        });
    }

    public function update(Lot $lot, array $data): Lot
    {
        return DB::transaction(function () use ($lot, $data) {
            $fresh = Lot::query()->lockForUpdate()->findOrFail($lot->id);
            if ($fresh->status === 'voided') {
                throw new BusinessException('No se puede modificar un lote anulado.');
            }
            if (blank($data['code'] ?? null)) {
                unset($data['code']);
            }
            // Ya liquidado al productor: kilos y precio quedan fijos (para corregir, anular la liquidación).
            if ($fresh->settled_at) {
                foreach (['kg_received', 'price_per_kg', 'producer_id'] as $key) {
                    if (array_key_exists($key, $data) && (string) ($data[$key] ?? '') !== (string) ($fresh->getRawOriginal($key) ?? '')
                        && (float) ($data[$key] ?? 0) !== (float) ($fresh->getRawOriginal($key) ?? 0)) {
                        throw new BusinessException('El lote ya fue liquidado al productor: para cambiar kilos, precio o productor, anulá la liquidación desde su cuenta corriente.');
                    }
                }
            }
            $fresh->update($data);

            return $fresh;
        });
    }

    public function close(Lot $lot, ?string $notes = null): Lot
    {
        return $this->transition($lot, ['open'], 'closed', $notes, 'close', 'Cerró el lote '.$lot->code);
    }

    public function void(Lot $lot, string $reason): Lot
    {
        return DB::transaction(function () use ($lot, $reason) {
            $activeCrates = Crate::query()->where('lot_id', $lot->id)->where('status', '!=', 'voided')->exists();
            $activePallets = Pallet::query()->where('lot_id', $lot->id)->where('status', '!=', 'voided')->exists();
            if ($activeCrates || $activePallets) {
                throw new BusinessException('El lote tiene pallets o cajones activos. Anulalos o reasignalos antes de anular el lote.');
            }

            return $this->transition($lot, ['open', 'closed'], 'voided', $reason, 'void', 'Anuló el lote '.$lot->code);
        });
    }

    /**
     * Totales del lote para la ficha.
     *
     * @return array{pallets: int, crates: int, processed_kg: float, records: int, rejects: int, rejected_kg: float}
     */
    public function summary(Lot $lot): array
    {
        $production = ProductionRecord::query()
            ->join('crates', 'crates.id', '=', 'production_records.crate_id')
            ->where('crates.lot_id', $lot->id)
            ->whereNull('production_records.voided_at')
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(production_records.weight), 0) as kg')
            ->first();

        $rejects = Reject::query()->where('lot_id', $lot->id)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(weight), 0) as kg')->first();

        return [
            'pallets' => Pallet::query()->where('lot_id', $lot->id)->count(),
            'crates' => Crate::query()->where('lot_id', $lot->id)->count(),
            'records' => (int) $production->records,
            'processed_kg' => (float) $production->kg,
            'rejects' => (int) $rejects->total,
            'rejected_kg' => (float) $rejects->kg,
        ];
    }

    /**
     * Romaneo del lote: kilos empacados por variedad, calibre y selección, rechazos por motivo y rinde
     * contra los kilos recibidos del productor.
     *
     * @return array{rows: \Illuminate\Support\Collection, rejects: \Illuminate\Support\Collection, summary: array, yield_pct: ?float}
     */
    public function romaneo(Lot $lot): array
    {
        $rows = ProductionRecord::query()
            ->join('crates', 'crates.id', '=', 'production_records.crate_id')
            ->join('varieties', 'varieties.id', '=', 'production_records.variety_id')
            ->join('sizes', 'sizes.id', '=', 'production_records.size_id')
            ->leftJoin('grades', 'grades.id', '=', 'crates.grade_id')
            ->where('crates.lot_id', $lot->id)
            ->whereNull('production_records.voided_at')
            ->groupBy('varieties.name', 'sizes.name', 'sizes.sort', 'grades.name', 'grades.sort_order')
            ->orderBy('varieties.name')->orderBy('sizes.sort')->orderBy('grades.sort_order')
            ->selectRaw('varieties.name as variety, sizes.name as size, grades.name as grade, COUNT(*) as crates, SUM(production_records.weight) as kg')
            ->toBase()->get();

        $rejects = Reject::query()->where('rejects.lot_id', $lot->id)
            ->leftJoin('reasons', 'reasons.id', '=', 'rejects.reason_id')
            ->groupBy('reasons.name')->selectRaw('reasons.name as reason, COUNT(*) as total, SUM(rejects.weight) as kg')
            ->toBase()->get();

        $summary = $this->summary($lot);
        $received = (float) $lot->kg_received;

        return [
            'rows' => $rows,
            'rejects' => $rejects,
            'summary' => $summary,
            'yield_pct' => $received > 0 ? round($summary['processed_kg'] / $received * 100, 1) : null,
            'waste_pct' => $received > 0 ? round($summary['rejected_kg'] / $received * 100, 1) : null,
        ];
    }

    /** @param  list<string>  $from */
    private function transition(Lot $lot, array $from, string $to, ?string $notes, string $action, string $description): Lot
    {
        return DB::transaction(function () use ($lot, $from, $to, $notes, $action, $description) {
            $current = Lot::query()->whereKey($lot->id)->value('status');

            if (! in_array($current, $from, true)) {
                throw new BusinessException('El lote está '.mb_strtolower(Lot::STATUSES[$current] ?? $current)
                    .' y no admite esta operación.');
            }

            $affected = Lot::query()->whereKey($lot->id)->where('status', $current)
                ->toBase()->update(['status' => $to, 'updated_at' => now()]);
            if ($affected !== 1) {
                throw new ConcurrencyException;
            }

            StateHistory::query()->create([
                'stateful_type' => $lot->getMorphClass(),
                'stateful_id' => $lot->id,
                'from_state' => $current,
                'to_state' => $to,
                'user_id' => auth()->id(),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $this->audit->log($action, $lot, ['status' => $current], ['status' => $to], $description, $notes);

            return $lot->refresh();
        });
    }
}
