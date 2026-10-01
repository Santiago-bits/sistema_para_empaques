<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Pallet;
use App\Models\QualityControl;
use App\Models\Reason;
use App\Models\Reject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controles de calidad y rechazos (merma).
 *
 * - Control sobre un cajón: registra el control y cambia el estado del cajón
 *   (Procesado/En control → Aprobado/Rechazado) vía StateTransitionService,
 *   todo en una sola transacción. Si el resultado es rechazado puede registrar
 *   el rechazo con motivo y peso (variedad/tamaño/lote/embalador del cajón).
 * - Control sobre un lote o pallet: registra el control y, opcionalmente,
 *   aplica el resultado en bloque a los cajones elegibles (en chunks, cada
 *   cajón con su propia transición), informando aplicados y no aplicados.
 */
class QualityService
{
    /** Estados desde los que un cajón puede aprobarse/rechazarse en bloque. */
    public const BULK_ELIGIBLE = [CrateStatus::Processed, CrateStatus::InControl];

    private const CHUNK = 200;

    public function __construct(
        private readonly StateTransitionService $transitions,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Busca el objeto a controlar por código (cajón, pallet o lote, en ese orden).
     *
     * @return array{type: string, model: Crate|Pallet|Lot}|null
     */
    public function findTarget(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $crate = Crate::query()->where('code', $code)->orWhere('barcode', $code)->first();
        if ($crate) {
            return ['type' => 'crate', 'model' => $crate];
        }
        $pallet = Pallet::query()->where('code', $code)->orWhere('barcode', $code)->first();
        if ($pallet) {
            return ['type' => 'pallet', 'model' => $pallet];
        }
        $lot = Lot::query()->where('code', $code)->first();

        return $lot ? ['type' => 'lot', 'model' => $lot] : null;
    }

    /**
     * Registra un control de calidad.
     *
     * $data: target_type (crate|lot|pallet), target_id, result (approved|rejected|observed), grade, caliber,
     * ripeness, damage_pct, bruise_pct, rot_pct, reject_pct, defects, notes, controlled_at,
     * register_reject (bool), reason_id, reject_weight, apply_to_crates (bool, lote/pallet).
     *
     * @return array{control: QualityControl, applied: int, skipped: int, rejects: int, message: string}
     */
    public function record(array $data): array
    {
        $type = $data['target_type'] ?? null;
        $result = $data['result'] ?? null;

        if (! in_array($type, ['crate', 'lot', 'pallet'], true)) {
            throw new BusinessException('Indicá qué se controla: cajón, lote o pallet.');
        }
        if (! array_key_exists($result, QualityControl::RESULTS)) {
            throw new BusinessException('Resultado de control inválido.');
        }

        $registerReject = (bool) ($data['register_reject'] ?? false);
        $reason = $registerReject ? $this->rejectReason($data['reason_id'] ?? null) : null;

        return $type === 'crate'
            ? $this->recordForCrate((int) $data['target_id'], $data, $reason)
            : $this->recordForGroup($type, (int) $data['target_id'], $data, $reason);
    }

    /** Registra un rechazo (merma) sin control asociado, p.ej. desde la pantalla de rechazos. */
    public function registerReject(array $data): Reject
    {
        $reason = $this->rejectReason($data['reason_id'] ?? null);

        return DB::transaction(function () use ($data, $reason) {
            $crate = null;
            if (! empty($data['crate_id'])) {
                $crate = Crate::query()->whereKey($data['crate_id'])->first();
                if (! $crate) {
                    throw new BusinessException('El cajón indicado no existe.');
                }
            }

            $weight = isset($data['weight']) && $data['weight'] !== '' ? (float) $data['weight'] : (float) ($crate?->weight ?? 0);
            if ($weight <= 0) {
                throw new BusinessException('Indicá el peso rechazado (kg).');
            }

            return Reject::query()->create([
                'crate_id' => $crate?->id,
                'lot_id' => $crate?->lot_id ?? ($data['lot_id'] ?? null),
                'variety_id' => $crate?->variety_id ?? ($data['variety_id'] ?? null),
                'size_id' => $crate?->size_id ?? ($data['size_id'] ?? null),
                'packer_id' => $crate?->packer_id ?? ($data['packer_id'] ?? null),
                'reason_id' => $reason->id,
                'quality_control_id' => $data['quality_control_id'] ?? null,
                'weight' => $weight,
                'user_id' => auth()->id(),
                'rejected_at' => $this->date($data['rejected_at'] ?? null),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    private function recordForCrate(int $crateId, array $data, ?Reason $reason): array
    {
        return DB::transaction(function () use ($crateId, $data, $reason) {
            $crate = Crate::query()->whereKey($crateId)->lockForUpdate()->first();
            if (! $crate) {
                throw new BusinessException('El cajón indicado no existe.');
            }

            $control = QualityControl::query()->create($this->controlAttributes($data, ['crate_id' => $crate->id]));
            $this->applyResultToCrate($crate, $data['result'], $control);

            $rejects = 0;
            if ($reason && $data['result'] === 'rejected') {
                $this->registerReject([
                    'crate_id' => $crate->id,
                    'reason_id' => $reason->id,
                    'quality_control_id' => $control->id,
                    'weight' => $data['reject_weight'] ?? null,
                    'rejected_at' => $control->controlled_at,
                    'notes' => $data['notes'] ?? null,
                ]);
                $rejects = 1;
            }

            $label = QualityControl::RESULTS[$data['result']];

            return [
                'control' => $control,
                'applied' => 1,
                'skipped' => 0,
                'rejects' => $rejects,
                'message' => "Cajón {$crate->code}: control registrado ({$label})".($rejects ? ' y rechazo cargado.' : '.'),
            ];
        });
    }

    private function recordForGroup(string $type, int $id, array $data, ?Reason $reason): array
    {
        $model = $type === 'lot' ? Lot::query()->find($id) : Pallet::query()->find($id);
        if (! $model) {
            throw new BusinessException($type === 'lot' ? 'El lote indicado no existe.' : 'El pallet indicado no existe.');
        }

        $result = $data['result'];
        $bulk = (bool) ($data['apply_to_crates'] ?? false) && in_array($result, ['approved', 'rejected'], true);
        $fk = $type === 'lot' ? 'lot_id' : 'pallet_id';
        $label = ($type === 'lot' ? 'Lote ' : 'Pallet ').$model->code;

        // 1) El control (y un eventual rechazo global del lote/pallet) en una transacción.
        $control = DB::transaction(function () use ($data, $fk, $model, $reason, $bulk, $type) {
            $control = QualityControl::query()->create($this->controlAttributes($data, [$fk => $model->id]));

            if ($reason && $data['result'] === 'rejected' && ! $bulk) {
                $this->registerReject([
                    'lot_id' => $type === 'lot' ? $model->id : $model->lot_id,
                    'variety_id' => $model->variety_id,
                    'reason_id' => $reason->id,
                    'quality_control_id' => $control->id,
                    'weight' => $data['reject_weight'] ?? null,
                    'rejected_at' => $control->controlled_at,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            return $control;
        });

        if (! $bulk) {
            return [
                'control' => $control,
                'applied' => 0,
                'skipped' => 0,
                'rejects' => ($reason && $result === 'rejected') ? 1 : 0,
                'message' => "{$label}: control registrado (".QualityControl::RESULTS[$result].').',
            ];
        }

        // 2) Aplicación en bloque: cada cajón con su propia transición atómica.
        $applied = 0;
        $failed = 0;
        $rejects = 0;
        $eligible = array_map(fn (CrateStatus $s) => $s->value, self::BULK_ELIGIBLE);
        $total = Crate::query()->where($fk, $model->id)->where('status', '!=', CrateStatus::Voided->value)->count();

        Crate::query()->where($fk, $model->id)->whereIn('status', $eligible)
            ->chunkById(self::CHUNK, function ($crates) use ($result, $control, $reason, &$applied, &$failed, &$rejects) {
                foreach ($crates as $crate) {
                    try {
                        DB::transaction(function () use ($crate, $result, $control, $reason, &$rejects) {
                            $this->applyResultToCrate($crate, $result, $control);
                            if ($reason && $result === 'rejected' && (float) $crate->weight > 0) {
                                $this->registerReject([
                                    'crate_id' => $crate->id,
                                    'reason_id' => $reason->id,
                                    'quality_control_id' => $control->id,
                                    'rejected_at' => $control->controlled_at,
                                ]);
                                $rejects++;
                            }
                        });
                        $applied++;
                    } catch (BusinessException) {
                        // Cambió de estado o lo modificó otro usuario: se informa como no aplicado.
                        $failed++;
                    }
                }
            });

        $skipped = max(0, $total - $applied);
        $this->audit->log('quality_bulk', $model, null, [
            'result' => $result, 'applied' => $applied, 'skipped' => $skipped, 'quality_control_id' => $control->id,
        ], "Control de calidad en bloque: {$applied} cajones aplicados, {$skipped} sin cambios.");

        $message = "{$label}: control registrado. Se marcaron {$applied} cajones como «".QualityControl::RESULTS[$result].'»';
        $message .= $skipped > 0 ? "; {$skipped} no se modificaron (estado no elegible o modificados por otro usuario)." : '.';

        return ['control' => $control, 'applied' => $applied, 'skipped' => $skipped, 'rejects' => $rejects, 'message' => $message];
    }

    /** Cambia estado y quality_status del cajón según el resultado del control. */
    private function applyResultToCrate(Crate $crate, string $result, QualityControl $control): void
    {
        $notes = 'Control de calidad #'.$control->id;
        $current = $crate->status;

        if ($result === 'observed') {
            // Observado: queda "En control" si la transición es válida; si no, sólo se registra el control.
            if ($current !== CrateStatus::InControl && $current->canTransitionTo(CrateStatus::InControl)) {
                $this->transitions->transition($crate, CrateStatus::InControl, $notes, ['quality_status' => 'pending']);
            }

            return;
        }

        $target = $result === 'approved' ? CrateStatus::Approved : CrateStatus::Rejected;

        if ($current === $target) {
            // Re-control que confirma el estado: sólo se sincroniza quality_status.
            Crate::query()->whereKey($crate->id)->update(['quality_status' => $result, 'updated_at' => now()]);

            return;
        }

        try {
            $this->transitions->transition($crate, $target, $notes, ['quality_status' => $result]);
        } catch (InvalidTransitionException) {
            throw new BusinessException(
                "El cajón {$crate->code} está «{$current->label()}»: no se puede marcar como «{$target->label()}». "
                .'Sólo se aprueban o rechazan cajones procesados o en control.',
                'invalid_transition'
            );
        }
    }

    private function controlAttributes(array $data, array $target): array
    {
        return array_merge([
            'crate_id' => null,
            'lot_id' => null,
            'pallet_id' => null,
            'result' => $data['result'],
            'grade' => $data['grade'] ?? null,
            'caliber' => $data['caliber'] ?? null,
            'ripeness' => $data['ripeness'] ?? null,
            'damage_pct' => (float) ($data['damage_pct'] ?? 0),
            'bruise_pct' => (float) ($data['bruise_pct'] ?? 0),
            'rot_pct' => (float) ($data['rot_pct'] ?? 0),
            'reject_pct' => (float) ($data['reject_pct'] ?? 0),
            'defects' => $data['defects'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => auth()->id(),
            'controlled_at' => $this->date($data['controlled_at'] ?? null),
        ], $target);
    }

    private function rejectReason(mixed $id): Reason
    {
        $reason = $id ? Reason::query()->whereKey($id)->where('type', 'reject')->where('active', true)->first() : null;
        if (! $reason) {
            throw new BusinessException('Seleccioná un motivo de rechazo válido.');
        }

        return $reason;
    }

    private function date(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return $value ? Carbon::parse($value) : now();
    }
}
