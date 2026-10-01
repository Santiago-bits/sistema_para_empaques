<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Pallet;
use App\Models\ProductionRecord;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cajones: alta manual / masiva, edición con motivo de campos críticos,
 * anulación y configuración de campos obligatorios/opcionales/ocultos.
 */
class CrateService
{
    /** Campos cuyo cambio exige motivo y queda auditado con valor anterior/nuevo. */
    public const CRITICAL_FIELDS = ['weight', 'variety_id', 'size_id', 'packer_id'];

    /** Campos configurables desde Configuración → Campos (setting fields.crate). */
    public const CONFIGURABLE_FIELDS = [
        'weight' => 'Peso',
        'lot_id' => 'Lote',
        'pallet_id' => 'Pallet',
        'variety_id' => 'Variedad',
        'size_id' => 'Tamaño',
        'packer_id' => 'Embalador',
        'barcode' => 'Código de barras',
        'location_id' => 'Ubicación',
        'notes' => 'Observaciones',
    ];

    /** Estados en los que el cajón ya no se puede editar. */
    public const LOCKED_STATUSES = [CrateStatus::Reserved, CrateStatus::Loaded, CrateStatus::Dispatched, CrateStatus::Invoiced, CrateStatus::Voided];

    public function __construct(
        private readonly SequenceService $sequences,
        private readonly StateTransitionService $states,
        private readonly AuditService $audit,
    ) {
    }

    /** required | optional | hidden según setting('fields.crate'). Por defecto: optional. */
    public function fieldMode(string $field): string
    {
        $config = (array) setting('fields.crate', []);
        $mode = $config[$field] ?? 'optional';

        return in_array($mode, ['required', 'optional', 'hidden'], true) ? $mode : 'optional';
    }

    /** @return array<string, string> campo => modo */
    public function fieldModes(): array
    {
        $modes = [];
        foreach (array_keys(self::CONFIGURABLE_FIELDS) as $field) {
            $modes[$field] = $this->fieldMode($field);
        }

        return $modes;
    }

    public function isLocked(Crate $crate): bool
    {
        return in_array($crate->status, self::LOCKED_STATUSES, true) || $crate->current_load_id !== null;
    }

    public function create(array $data, User $user): Crate
    {
        return DB::transaction(function () use ($data, $user) {
            $data = $this->inherit($data);
            $code = trim((string) ($data['code'] ?? '')) ?: $this->sequences->next('crate');

            $crate = Crate::query()->create(array_merge($this->only($data), [
                'code' => $code,
                'status' => CrateStatus::Registered,
                'quality_status' => 'pending',
                'season_id' => Season::current()?->id,
                'created_by' => $user->id,
            ]));

            $this->states->recordInitial($crate, CrateStatus::Registered, 'Alta manual');

            return $crate;
        });
    }

    /**
     * Genera N cajones con numeración correlativa (para preimprimir etiquetas).
     *
     * @return Collection<int, Crate>
     */
    public function createBatch(int $quantity, array $data, User $user): Collection
    {
        if ($quantity < 1 || $quantity > LabelService::MAX_LABELS) {
            throw new BusinessException('La cantidad debe estar entre 1 y '.LabelService::MAX_LABELS.'.');
        }

        return DB::transaction(function () use ($quantity, $data, $user) {
            $crates = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $crates->push($this->create(array_merge($data, ['code' => null]), $user));
            }

            return $crates;
        });
    }

    /**
     * Edita un cajón. Si cambia un campo crítico exige motivo (queda en la auditoría
     * con valor anterior y nuevo) y sincroniza el registro de producción vigente.
     */
    public function update(Crate $crate, array $data, ?string $reason): Crate
    {
        return DB::transaction(function () use ($crate, $data, $reason) {
            /** @var Crate $locked */
            $locked = Crate::query()->whereKey($crate->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['version']) && (int) $data['version'] !== (int) $locked->version) {
                throw new ConcurrencyException;
            }
            if ($this->isLocked($locked)) {
                throw new BusinessException('El cajón está '.mb_strtolower($locked->status->label()).' y ya no se puede modificar.');
            }

            $values = $this->only($this->inherit($data, $locked));
            $locked->fill($values);
            $criticalChanges = array_intersect(array_keys($locked->getDirty()), self::CRITICAL_FIELDS);

            if ($criticalChanges !== [] && trim((string) $reason) === '') {
                throw new BusinessException('Indicá el motivo del cambio de peso, variedad, tamaño o embalador.', 'reason_required');
            }
            if (! $locked->isDirty()) {
                return $locked;
            }

            $this->audit->withReason($reason ? trim($reason) : null);
            $locked->version = $locked->version + 1;
            $locked->save();

            if ($criticalChanges !== []) {
                $this->syncProductionRecord($locked);
            }

            return $locked;
        });
    }

    public function void(Crate $crate, string $reason, User $user): Crate
    {
        return DB::transaction(function () use ($crate, $reason, $user) {
            /** @var Crate $locked */
            $locked = Crate::query()->whereKey($crate->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->current_load_id !== null) {
                throw new BusinessException('El cajón está asignado a una carga: quitalo de la carga antes de anularlo.');
            }

            $this->audit->withReason($reason);
            $voided = $this->states->transition($locked, CrateStatus::Voided, $reason);

            // La producción de un cajón anulado deja de contar en estadísticas.
            ProductionRecord::query()->where('crate_id', $locked->id)->whereNull('voided_at')->get()
                ->each(fn (ProductionRecord $record) => $record->update([
                    'voided_at' => now(),
                    'voided_by' => $user->id,
                    'void_reason' => 'Cajón anulado: '.$reason,
                ]));

            return $voided;
        });
    }

    /** Busca un cajón por código o código de barras (incluye eliminados lógicamente si se pide). */
    public function findByCode(string $code, bool $withTrashed = false): ?Crate
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return Crate::query()
            ->when($withTrashed, fn ($q) => $q->withTrashed())
            ->where(fn ($q) => $q->where('code', $code)->orWhere('barcode', $code))
            ->first();
    }

    private function syncProductionRecord(Crate $crate): void
    {
        $record = ProductionRecord::query()->where('crate_id', $crate->id)->whereNull('voided_at')->latest('id')->first();
        if (! $record) {
            return;
        }

        $changes = array_filter([
            'weight' => $crate->weight,
            'variety_id' => $crate->variety_id,
            'size_id' => $crate->size_id,
            'packer_id' => $crate->packer_id,
        ], fn ($v) => $v !== null);

        $record->update($changes);
    }

    /** Completa lote/productor/propietario/variedad desde el pallet o el lote si no se indicaron. */
    private function inherit(array $data, ?Crate $current = null): array
    {
        if (! empty($data['pallet_id']) && (int) $data['pallet_id'] !== (int) $current?->pallet_id
            && ($pallet = Pallet::query()->find($data['pallet_id']))) {
            foreach (['lot_id', 'producer_id', 'owner_id', 'variety_id'] as $field) {
                if (empty($data[$field]) && empty($current?->{$field})) {
                    $data[$field] = $pallet->{$field};
                }
            }
        }
        if (! empty($data['lot_id']) && ($lot = Lot::query()->find($data['lot_id']))) {
            foreach (['producer_id', 'owner_id'] as $field) {
                if (empty($data[$field]) && empty($current?->{$field})) {
                    $data[$field] = $lot->{$field};
                }
            }
        }

        return $data;
    }

    private function only(array $data): array
    {
        $fields = ['barcode', 'pallet_id', 'lot_id', 'producer_id', 'owner_id', 'variety_id', 'size_id', 'packer_id',
            'weight', 'location_id', 'notes'];

        // Los campos ocultos por configuración no se modifican desde formularios.
        $fields = array_values(array_filter($fields, fn ($f) => ! isset(self::CONFIGURABLE_FIELDS[$f]) || $this->fieldMode($f) !== 'hidden'));

        return array_intersect_key($data, array_flip($fields));
    }
}
