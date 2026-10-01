<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\PalletStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Models\Crate;
use App\Models\LocationMovement;
use App\Models\Lot;
use App\Models\Pallet;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ingreso de pallets: alta (código automático o escaneado), edición con
 * movimiento de ubicación y anulación con motivo.
 */
class PalletService
{
    /** Estados de cajón que impiden anular el pallet. */
    public const BLOCKING_CRATE_STATUSES = [CrateStatus::Reserved, CrateStatus::Loaded, CrateStatus::Dispatched, CrateStatus::Invoiced];

    public function __construct(
        private readonly SequenceService $sequences,
        private readonly StateTransitionService $states,
        private readonly AuditService $audit,
    ) {
    }

    public function create(array $data, User $user): Pallet
    {
        return DB::transaction(function () use ($data, $user) {
            $data = $this->inheritFromLot($data);
            $code = trim((string) ($data['code'] ?? '')) ?: $this->sequences->next('pallet');

            $pallet = Pallet::query()->create(array_merge($this->only($data), [
                'code' => $code,
                'status' => PalletStatus::Received,
                'season_id' => Season::current()?->id,
                'received_at' => $data['received_at'] ?? now(),
                'created_by' => $user->id,
            ]));

            $this->states->recordInitial($pallet, PalletStatus::Received, 'Ingreso del pallet');

            if ($pallet->location_id) {
                $this->recordMovement($pallet, null, $pallet->location_id, $user, 'Ubicación inicial');
            }

            return $pallet;
        });
    }

    public function update(Pallet $pallet, array $data, User $user): Pallet
    {
        return DB::transaction(function () use ($pallet, $data, $user) {
            /** @var Pallet $locked */
            $locked = Pallet::query()->whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['version']) && (int) $data['version'] !== (int) $locked->version) {
                throw new ConcurrencyException;
            }
            if (in_array($locked->status, [PalletStatus::Voided, PalletStatus::Dispatched], true)) {
                throw new BusinessException('No se puede editar un pallet '.mb_strtolower($locked->status->label()).'.');
            }

            $fromLocation = $locked->location_id;
            $locked->fill($this->only($data));
            $locked->version = $locked->version + 1;
            $locked->save();

            if ((int) $fromLocation !== (int) $locked->location_id) {
                $this->recordMovement($locked, $fromLocation, $locked->location_id, $user, 'Cambio de ubicación');
            }

            return $locked;
        });
    }

    public function void(Pallet $pallet, string $reason): Pallet
    {
        return DB::transaction(function () use ($pallet, $reason) {
            /** @var Pallet $locked */
            $locked = Pallet::query()->whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            $blocking = Crate::query()->where('pallet_id', $locked->id)
                ->where(fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::BLOCKING_CRATE_STATUSES))
                    ->orWhereNotNull('current_load_id'))
                ->count();
            if ($blocking > 0) {
                throw new BusinessException("No se puede anular: el pallet tiene {$blocking} cajón(es) asignados a cargas o despachados.");
            }

            $this->audit->withReason($reason);

            return $this->states->transition($locked, PalletStatus::Voided, $reason);
        });
    }

    /** Si se indicó un lote, completa productor/propietario/variedad faltantes desde el lote. */
    private function inheritFromLot(array $data): array
    {
        if (! empty($data['lot_id']) && ($lot = Lot::query()->find($data['lot_id']))) {
            $data['producer_id'] = $data['producer_id'] ?? $lot->producer_id;
            $data['owner_id'] = $data['owner_id'] ?? $lot->owner_id;
            $data['variety_id'] = $data['variety_id'] ?? $lot->variety_id;
            $data['origin'] = $data['origin'] ?? $lot->origin;
        }

        return $data;
    }

    private function only(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'barcode', 'lot_id', 'producer_id', 'owner_id', 'variety_id', 'origin', 'received_at', 'quantity',
            'gross_weight', 'location_id', 'notes',
        ]));
    }

    private function recordMovement(Pallet $pallet, ?int $from, ?int $to, User $user, string $notes): void
    {
        LocationMovement::query()->create([
            'movable_type' => $pallet->getMorphClass(),
            'movable_id' => $pallet->id,
            'from_location_id' => $from,
            'to_location_id' => $to,
            'user_id' => $user->id,
            'notes' => $notes,
            'moved_at' => now(),
        ]);
    }
}
