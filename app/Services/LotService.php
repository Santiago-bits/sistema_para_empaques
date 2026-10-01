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
