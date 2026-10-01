<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\PalletStatus;
use App\Events\CrateProcessed;
use App\Exceptions\BusinessException;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Pallet;
use App\Models\ProductionRecord;
use App\Models\ProductionStoppage;
use App\Models\Season;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registro de producción desde el modo escaneo.
 *
 * Garantías:
 *  - Idempotencia: la misma idempotency_key devuelve el mismo resultado sin duplicar
 *    (unique en production_records.idempotency_key + verificación dentro del lock).
 *  - Un cajón se procesa una sola vez: SELECT ... FOR UPDATE sobre el cajón y
 *    transición Registrado → Procesado con bloqueo optimista (version).
 *  - Peso fuera de rango sólo con autorización de un supervisor (production.authorize).
 */
class ProductionService
{
    /** Reasons de error que la pantalla de escaneo interpreta. */
    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_AUTHORIZATION = 'requires_authorization';

    public function __construct(
        private readonly StateTransitionService $states,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * @param  array{crate_code: string, packer_code: string, weight: float|string, variety_id: int, size_id: int,
     *               production_line_id?: ?int, lot_id?: ?int, idempotency_key?: ?string, weight_source?: ?string,
     *               supervisor_login?: ?string, supervisor_password?: ?string, authorization_reason?: ?string,
     *               notes?: ?string}  $data
     * @return array{replay: bool, record: ProductionRecord, crate: Crate}
     */
    public function register(array $data, User $user): array
    {
        $key = isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $key = $key === '' ? null : $key;

        if ($key && ($existing = $this->findByKey($key, $user))) {
            return ['replay' => true, 'record' => $existing, 'crate' => $existing->crate];
        }

        // Las credenciales del supervisor se verifican fuera de la transacción para que
        // los intentos fallidos queden auditados aunque el registro no se complete.
        $weight = round((float) ($data['weight'] ?? 0), 2);
        $supervisor = $this->isOutOfRange($weight) ? $this->verifySupervisor($data, $user, $weight) : null;

        try {
            $result = DB::transaction(fn () => $this->registerInTransaction($data, $user, $key, $supervisor), 3);
        } catch (UniqueConstraintViolationException $e) {
            // Carrera entre dos reintentos con la misma clave: el otro ya lo guardó.
            if ($key && ($existing = $this->findByKey($key, $user))) {
                return ['replay' => true, 'record' => $existing, 'crate' => $existing->crate];
            }
            throw $e;
        }

        if (! $result['replay']) {
            event(new CrateProcessed($result['crate']));
        }

        return $result;
    }

    private function registerInTransaction(array $data, User $user, ?string $key, ?User $supervisor): array
    {
        $code = trim((string) $data['crate_code']);
        $crate = $this->lockCrate($code);

        // Si otro reintento con la misma clave terminó mientras esperábamos el lock.
        if ($key && ($existing = $this->findByKey($key, $user))) {
            return ['replay' => true, 'record' => $existing, 'crate' => $existing->crate];
        }

        if (! $crate) {
            if (! setting('production.auto_create_crate', true)) {
                throw new BusinessException("El cajón {$code} no existe. Registralo antes de escanearlo.", 'crate_not_found', ['field' => 'crate']);
            }
            $crate = $this->autoCreateCrate($code, $user, $data);
        }

        $this->assertCrateAvailable($crate);
        $packer = $this->resolvePacker((string) $data['packer_code']);

        $lotId = ! empty($data['lot_id']) ? (int) $data['lot_id'] : $crate->lot_id;
        if (! $lotId && setting('production.require_lot', false)) {
            throw new BusinessException('Indicá el lote: es obligatorio para registrar producción.', 'lot_required', ['field' => 'lot']);
        }
        $lot = $lotId ? Lot::query()->find($lotId) : null;
        if ($lotId && (! $lot || $lot->status === 'voided')) {
            throw new BusinessException('El lote indicado no existe o está anulado.', 'lot_invalid', ['field' => 'lot']);
        }

        $weight = round((float) $data['weight'], 2);
        $authorizer = $this->authorizeWeight($weight, $data, $crate, $supervisor);

        $now = now();
        $shift = Shift::forTime($now);

        $record = ProductionRecord::query()->create([
            'crate_id' => $crate->id,
            'packer_id' => $packer->id,
            'variety_id' => (int) $data['variety_id'],
            'size_id' => (int) $data['size_id'],
            'shift_id' => $shift?->id,
            'production_line_id' => $data['production_line_id'] ?? null,
            'weight' => $weight,
            'weight_source' => ($data['weight_source'] ?? 'manual') === 'scale' ? 'scale' : 'manual',
            'user_id' => $user->id,
            'recorded_at' => $now,
            'idempotency_key' => $key,
            'authorized_by' => $authorizer?->id,
            'authorization_reason' => $authorizer ? trim((string) $data['authorization_reason']) : null,
            'notes' => $data['notes'] ?? null,
        ]);

        $extra = [
            'packer_id' => $packer->id,
            'variety_id' => (int) $data['variety_id'],
            'size_id' => (int) $data['size_id'],
            'weight' => $weight,
            'shift_id' => $shift?->id,
            'production_line_id' => $data['production_line_id'] ?? null,
            'processed_at' => $now,
            'processed_by' => $user->id,
        ];
        if ($lot && ! $crate->lot_id) {
            $extra['lot_id'] = $lot->id;
            $extra['producer_id'] = $crate->producer_id ?: $lot->producer_id;
            $extra['owner_id'] = $crate->owner_id ?: $lot->owner_id;
        }
        if (! $crate->season_id && ($season = Season::current())) {
            $extra['season_id'] = $season->id;
        }

        $notes = $authorizer ? 'Producción (peso autorizado por '.$authorizer->full_name.')' : 'Producción';
        $crate = $this->states->transition($crate, CrateStatus::Processed, $notes, $extra);

        $this->markPalletWithProduct($crate);

        return ['replay' => false, 'record' => $record, 'crate' => $crate];
    }

    private function lockCrate(string $code): ?Crate
    {
        if ($code === '') {
            throw new BusinessException('Escaneá el código del cajón.', 'crate_required', ['field' => 'crate']);
        }

        /** @var Crate|null $crate */
        $crate = Crate::query()->withTrashed()
            ->where(fn ($q) => $q->where('code', $code)->orWhere('barcode', $code))
            ->lockForUpdate()
            ->first();

        if ($crate?->trashed()) {
            throw new BusinessException("El cajón {$code} fue eliminado del sistema.", 'crate_deleted', ['field' => 'crate']);
        }

        return $crate;
    }

    private function autoCreateCrate(string $code, User $user, array $data): Crate
    {
        if (mb_strlen($code) > 40) {
            throw new BusinessException('El código escaneado es demasiado largo.', 'crate_invalid', ['field' => 'crate']);
        }

        try {
            // Savepoint: si otra PC creó el mismo cajón en paralelo, se descarta sólo este INSERT.
            $crate = DB::transaction(function () use ($code, $user, $data) {
                $crate = Crate::query()->create([
                    'code' => $code,
                    'status' => CrateStatus::Registered,
                    'quality_status' => 'pending',
                    'lot_id' => $data['lot_id'] ?? null,
                    'season_id' => Season::current()?->id,
                    'created_by' => $user->id,
                ]);
                $this->states->recordInitial($crate, CrateStatus::Registered, 'Alta automática por escaneo');

                return $crate;
            });
        } catch (UniqueConstraintViolationException) {
            $crate = $this->lockCrate($code);
            if (! $crate) {
                throw new BusinessException('No se pudo registrar el cajón. Intentá nuevamente.', 'crate_conflict', ['field' => 'crate']);
            }

            return $crate;
        }

        return Crate::query()->whereKey($crate->id)->lockForUpdate()->firstOrFail();
    }

    private function assertCrateAvailable(Crate $crate): void
    {
        if ($crate->status === CrateStatus::Voided) {
            throw new BusinessException("El cajón {$crate->code} está ANULADO.", 'crate_voided', ['field' => 'crate']);
        }
        if ($crate->status !== CrateStatus::Registered) {
            $when = $crate->processed_at ? ' el '.fdate($crate->processed_at, true) : '';
            $packer = $crate->packer ? ' (embalador '.$crate->packer->code.')' : '';

            throw new BusinessException(
                "DUPLICADO: el cajón {$crate->code} ya fue registrado{$when}{$packer}.",
                self::REASON_DUPLICATE,
                ['field' => 'crate', 'crate' => $crate->code, 'status' => $crate->status->value],
            );
        }
    }

    public function resolvePacker(string $code): Packer
    {
        $code = trim($code);
        if ($code === '') {
            throw new BusinessException('Escaneá el código del embalador.', 'packer_required', ['field' => 'packer']);
        }

        $packer = Packer::query()->where('code', $code)->first();
        if (! $packer) {
            throw new BusinessException("El embalador {$code} no existe.", 'packer_not_found', ['field' => 'packer']);
        }
        if (! $packer->active) {
            throw new BusinessException("El embalador {$packer->code} ({$packer->full_name}) está inactivo.", 'packer_inactive', ['field' => 'packer']);
        }

        return $packer;
    }

    public function isOutOfRange(float $weight): bool
    {
        $min = (float) setting('production.weight_min', 0);
        $max = (float) setting('production.weight_max', 0);

        return ($min > 0 && $weight < $min) || ($max > 0 && $weight > $max);
    }

    /**
     * Dentro de la transacción: valida el peso contra el rango configurado.
     * Fuera de rango sin supervisor verificado → requires_authorization.
     */
    private function authorizeWeight(float $weight, array $data, Crate $crate, ?User $supervisor): ?User
    {
        if ($weight <= 0) {
            throw new BusinessException('El peso debe ser mayor a cero.', 'weight_invalid', ['field' => 'weight']);
        }
        if (! $this->isOutOfRange($weight)) {
            return null;
        }

        $min = (float) setting('production.weight_min', 0);
        $max = (float) setting('production.weight_max', 0);

        if (! $supervisor) {
            throw new BusinessException(
                'Peso fuera de rango ('.num($weight, 2).' kg; permitido '.num($min, 2).' a '.num($max, 2).' kg). Requiere autorización de un supervisor.',
                self::REASON_AUTHORIZATION,
                ['field' => 'weight', 'min' => $min, 'max' => $max],
            );
        }

        $this->audit->log('authorize_weight', $crate, null, [
            'weight' => $weight, 'min' => $min, 'max' => $max, 'authorized_by' => $supervisor->id,
        ], 'Peso fuera de rango autorizado por '.$supervisor->full_name, trim((string) ($data['authorization_reason'] ?? '')));

        return $supervisor;
    }

    /**
     * Verifica usuario + contraseña de un supervisor con permiso production.authorize.
     * Devuelve null si no se enviaron credenciales. Nunca registra la contraseña.
     */
    private function verifySupervisor(array $data, User $operator, float $weight): ?User
    {
        $login = trim((string) ($data['supervisor_login'] ?? ''));
        $password = (string) ($data['supervisor_password'] ?? '');
        if ($login === '' && $password === '') {
            return null;
        }
        if ($login === '' || $password === '') {
            throw new BusinessException('Ingresá usuario y contraseña del supervisor.', 'authorization_failed', ['field' => 'supervisor']);
        }

        $reason = trim((string) ($data['authorization_reason'] ?? ''));
        if ($reason === '') {
            throw new BusinessException('Indicá el motivo de la autorización.', 'authorization_failed', ['field' => 'authorization_reason']);
        }

        $limiterKey = 'scan-authorize:'.$operator->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw new BusinessException('Demasiados intentos de autorización. Esperá un minuto.', 'authorization_throttled', ['field' => 'supervisor']);
        }

        $supervisor = User::query()
            ->where(fn ($q) => $q->where('username', $login)->orWhere('dni', $login)->orWhere('internal_code', $login))
            ->first();

        $valid = $supervisor && $supervisor->isActive() && Hash::check($password, $supervisor->password);
        $allowed = $valid && Gate::forUser($supervisor)->allows('production.authorize');

        if (! $allowed) {
            RateLimiter::hit($limiterKey, 60);
            $this->audit->log('authorization_failed', null, null, [
                'supervisor_login' => $login,
                'crate_code' => (string) ($data['crate_code'] ?? ''),
                'weight' => $weight,
            ], $valid ? 'Autorización rechazada: el usuario no tiene permiso' : 'Autorización rechazada: credenciales inválidas', $reason);

            throw new BusinessException(
                $valid ? 'El usuario indicado no tiene permiso para autorizar.' : 'Usuario o contraseña de supervisor incorrectos.',
                'authorization_failed',
                ['field' => 'supervisor'],
            );
        }

        RateLimiter::clear($limiterKey);

        return $supervisor;
    }

    private function markPalletWithProduct(Crate $crate): void
    {
        if (! $crate->pallet_id) {
            return;
        }
        $pallet = Pallet::query()->find($crate->pallet_id);
        if (! $pallet || ! in_array($pallet->status, [PalletStatus::Received, PalletStatus::Empty], true)) {
            return;
        }

        try {
            $this->states->transition($pallet, PalletStatus::WithProduct, 'Primer cajón procesado');
        } catch (BusinessException) {
            // Otro escaneo ya lo pasó a "Con producto": no afecta al registro del cajón.
        }
    }

    private function findByKey(string $key, User $user): ?ProductionRecord
    {
        $record = ProductionRecord::query()->with('crate', 'packer', 'variety', 'size', 'authorizer')->where('idempotency_key', $key)->first();
        if ($record && (int) $record->user_id !== (int) $user->id) {
            throw new BusinessException('La clave de operación pertenece a otro usuario. Recargá la pantalla.', 'idempotency_conflict');
        }

        return $record;
    }

    /**
     * Anula un registro de producción. El cajón vuelve a "Registrado" (transición
     * forzada y auditada) para poder registrarlo nuevamente.
     */
    public function voidRecord(ProductionRecord $record, string $reason, User $user): ProductionRecord
    {
        return DB::transaction(function () use ($record, $reason, $user) {
            /** @var ProductionRecord $locked */
            $locked = ProductionRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->voided_at !== null) {
                throw new BusinessException('El registro ya estaba anulado.');
            }

            /** @var Crate $crate */
            $crate = Crate::query()->whereKey($locked->crate_id)->lockForUpdate()->firstOrFail();
            if ($crate->current_load_id !== null || in_array($crate->status, [CrateStatus::Reserved, CrateStatus::Loaded, CrateStatus::Dispatched, CrateStatus::Invoiced], true)) {
                throw new BusinessException("No se puede anular: el cajón {$crate->code} está asignado a una carga o despachado.");
            }

            $this->audit->withReason($reason);
            $locked->update(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason]);

            if ($crate->status !== CrateStatus::Voided && $crate->status !== CrateStatus::Registered) {
                $this->states->transition($crate, CrateStatus::Registered, 'Registro de producción anulado: '.$reason, [
                    'packer_id' => null,
                    'weight' => null,
                    'processed_at' => null,
                    'processed_by' => null,
                    'quality_status' => 'pending',
                ], force: true);
            }

            return $locked->refresh();
        });
    }

    /** Fecha/hora de inicio del turno vigente (soporta turnos que cruzan medianoche). */
    public function shiftStart(?Shift $shift, ?Carbon $at = null): Carbon
    {
        $at ??= now();
        if (! $shift) {
            return $at->copy()->startOfDay();
        }
        [$h, $m] = array_map('intval', explode(':', (string) $shift->starts_at));
        $start = $at->copy()->setTime($h, $m);

        return $start->greaterThan($at) ? $start->subDay() : $start;
    }

    /**
     * Resumen del operador para la pantalla de escaneo: contadores del turno y
     * últimos registros.
     */
    public function operatorSummary(User $user, int $limit = 15): array
    {
        $shift = Shift::forTime();
        $from = $this->shiftStart($shift);

        $totals = ProductionRecord::query()->valid()->where('user_id', $user->id)->where('recorded_at', '>=', $from)
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

        $recent = ProductionRecord::query()->with('crate:id,code', 'packer:id,code,first_name,last_name', 'variety:id,name', 'size:id,name')
            ->where('user_id', $user->id)->latest('recorded_at')->latest('id')->limit($limit)->get()
            ->map(fn (ProductionRecord $r) => $this->presentRecord($r))->all();

        return [
            'shift' => $shift?->name,
            'totals' => ['crates' => (int) ($totals->crates ?? 0), 'kg' => round((float) ($totals->kg ?? 0), 2)],
            'recent' => $recent,
        ];
    }

    public function presentRecord(ProductionRecord $record): array
    {
        $record->loadMissing('crate', 'packer', 'variety', 'size');

        return [
            'id' => $record->id,
            'crate' => $record->crate?->code,
            'packer' => $record->packer?->code,
            'packer_name' => $record->packer?->full_name,
            'variety' => $record->variety?->name,
            'size' => $record->size?->name,
            'weight' => (float) $record->weight,
            'time' => $record->recorded_at?->format('H:i:s'),
            'authorized' => $record->authorized_by !== null,
            'voided' => $record->voided_at !== null,
        ];
    }

    /* ---------------------------------------------------------------- Paradas */

    public function startStoppage(array $data, User $user): ProductionStoppage
    {
        return DB::transaction(function () use ($data, $user) {
            $lineId = $data['production_line_id'] ?? null;

            // Una sola parada abierta por línea (o general): se bloquean las abiertas para evitar dos inicios simultáneos.
            $open = ProductionStoppage::query()->whereNull('ended_at')
                ->when($lineId, fn ($q) => $q->where('production_line_id', $lineId), fn ($q) => $q->whereNull('production_line_id'))
                ->lockForUpdate()->first();
            if ($open) {
                throw new BusinessException('Ya hay una parada en curso para esta línea. Finalizala antes de iniciar otra.');
            }

            $startedAt = isset($data['started_at']) ? Carbon::parse($data['started_at']) : now();

            return ProductionStoppage::query()->create([
                'production_line_id' => $lineId,
                'shift_id' => Shift::forTime($startedAt)?->id,
                'reason_id' => $data['reason_id'],
                'started_at' => $startedAt,
                'user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function finishStoppage(ProductionStoppage $stoppage, array $data): ProductionStoppage
    {
        return DB::transaction(function () use ($stoppage, $data) {
            /** @var ProductionStoppage $locked */
            $locked = ProductionStoppage::query()->whereKey($stoppage->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->ended_at !== null) {
                throw new BusinessException('La parada ya estaba finalizada.');
            }

            $endedAt = isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : now();
            if ($endedAt->lessThan($locked->started_at)) {
                throw new BusinessException('La hora de fin no puede ser anterior al inicio.');
            }

            $notes = trim(implode("\n", array_filter([$locked->notes, $data['notes'] ?? null])));
            $locked->update([
                'ended_at' => $endedAt,
                'duration_minutes' => (int) round($locked->started_at->diffInSeconds($endedAt, true) / 60),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            return $locked;
        });
    }
}
