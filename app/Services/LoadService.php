<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LoadStatus;
use App\Enums\PalletStatus;
use App\Enums\RemitoStatus;
use App\Events\LoadClosed;
use App\Events\LoadDispatched;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Crate;
use App\Models\DispatchCheck;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\LoadCrate;
use App\Models\Pallet;
use App\Models\Remito;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Armado, cierre, reapertura, cancelación y despacho de cargas.
 *
 * CONCURRENCIA (varios operadores armando cargas a la vez sobre el mismo stock):
 *  1. La carga se bloquea con SELECT ... FOR UPDATE durante toda la operación: asignar, quitar,
 *     cerrar y despachar sobre la MISMA carga quedan serializados.
 *  2. Cada cajón se toma con un UPDATE condicional
 *     (WHERE current_load_id IS NULL AND status IN (processed, approved) AND deleted_at IS NULL)
 *     y se verifica que haya afectado exactamente 1 fila: si otra carga lo tomó primero, 0 filas → rechazado.
 *  3. Segunda barrera en la base: load_crates.active_crate_id es UNIQUE; si por cualquier motivo
 *     quedara una asignación activa previa, el INSERT falla y ese cajón se revierte y se rechaza.
 *  4. Los cambios de estado de la carga usan StateTransitionService (estado + versión), así dos
 *     cierres simultáneos no pueden aplicarse ambos.
 */
class LoadService
{
    /** Máximo de cajones por operación de asignación (protege memoria y tiempo de transacción). */
    public const MAX_PER_REQUEST = 2000;

    private const CHUNK = 200;

    /** Campos de cabecera editables mientras la carga está en armado. */
    public const EDITABLE = [
        'date', 'truck_id', 'driver_id', 'transporter_id', 'destination_id', 'client_id', 'owner_id',
        'planned_crates', 'notes', 'trailer_plate', 'guide_number', 'commercial_destination', 'sales_channel',
        'sale_condition', 'freight_amount',
    ];

    public function __construct(
        private readonly StateTransitionService $transitions,
        private readonly AuditService $audit,
        private readonly SequenceService $sequences,
    ) {
    }

    // ------------------------------------------------------------------
    // Alta y edición
    // ------------------------------------------------------------------

    public function create(array $data, ?User $by = null): Load
    {
        return DB::transaction(function () use ($data, $by) {
            $load = Load::query()->create(array_merge(Arr::only($data, self::EDITABLE), [
                'number' => $this->sequences->next('load'),
                'date' => $data['date'] ?? today(),
                'status' => LoadStatus::Draft,
                'created_by' => $by?->id ?? auth()->id(),
            ]));
            $this->transitions->recordInitial($load, LoadStatus::Draft, 'Carga creada');

            return $load;
        });
    }

    public function update(Load $load, array $data): Load
    {
        return DB::transaction(function () use ($load, $data) {
            $locked = $this->lockDraft($load, 'Una carga cerrada no se puede modificar. Reabrila si tenés permiso.');
            $locked->fill(Arr::only($data, self::EDITABLE));
            if ($locked->isDirty()) {
                $locked->forceFill(['version' => $locked->version + 1])->save();
            }
            $load->refresh();

            return $load;
        });
    }

    // ------------------------------------------------------------------
    // Asignación de cajones
    // ------------------------------------------------------------------

    /**
     * Asigna cajones a una carga en armado.
     *
     * @param  list<int|string>  $crateIds
     * @return array{assigned: int, assigned_ids: list<int>, rejected: list<array{crate_id: int|null, code: string|null, reason: string}>}
     */
    public function assignCrates(Load $load, array $crateIds, User $by): array
    {
        $ids = $this->normalizeIds($crateIds);
        if (count($ids) > self::MAX_PER_REQUEST) {
            throw new BusinessException('Se pueden asignar como máximo '.num(self::MAX_PER_REQUEST).' cajones por operación.');
        }
        if ($ids === []) {
            return ['assigned' => 0, 'assigned_ids' => [], 'rejected' => []];
        }

        $result = DB::transaction(function () use ($load, $ids, $by) {
            $locked = $this->lockDraft($load, 'La carga no está en armado: no se pueden asignar cajones.');
            $now = now();
            $assignable = array_map(fn (CrateStatus $s) => $s->value, CrateStatus::assignable());
            $assigned = [];
            $rejected = [];
            $history = [];
            $palletIds = [];

            foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                $current = Crate::withTrashed()->whereIn('id', $chunk)
                    ->get(['id', 'code', 'status', 'current_load_id', 'pallet_id', 'warehouse_id', 'deleted_at'])->keyBy('id');
                $loadNumbers = Load::query()->whereIn('id', $current->pluck('current_load_id')->filter()->unique())
                    ->pluck('number', 'id');

                $taken = [];
                foreach ($chunk as $id) {
                    $crate = $current->get($id);
                    $reason = $this->rejectionReason($crate, $locked, $loadNumbers->all(), $assignable);
                    if ($reason !== null) {
                        $rejected[] = ['crate_id' => $id, 'code' => $crate?->code, 'reason' => $reason];
                        continue;
                    }

                    // UPDATE atómico condicional: sólo gana quien encuentra el cajón libre.
                    $affected = DB::table('crates')
                        ->where('id', $id)
                        ->whereNull('current_load_id')
                        ->where('status', $crate->status->value)
                        ->whereIn('status', $assignable)
                        ->whereNull('deleted_at')
                        ->update([
                            'current_load_id' => $locked->id,
                            'status' => CrateStatus::Reserved->value,
                            'version' => DB::raw('version + 1'),
                            'updated_at' => $now,
                        ]);

                    if ($affected !== 1) {
                        $rejected[] = ['crate_id' => $id, 'code' => $crate->code, 'reason' => $this->raceReason($id)];
                        continue;
                    }
                    $taken[$id] = $crate;
                }

                $this->insertAssignments($locked, $taken, $by, $now, $rejected);

                foreach ($taken as $id => $crate) {
                    $assigned[] = $id;
                    if ($crate->pallet_id) {
                        $palletIds[$crate->pallet_id] = true;
                    }
                    $history[] = $this->historyRow($id, $crate->status->value, CrateStatus::Reserved->value, $by, $now,
                        'Asignado a la carga '.$locked->number);
                }
            }

            $this->insertHistory($history);

            if ($assigned !== []) {
                $codes = Crate::query()->whereIn('id', array_slice($assigned, 0, 50))->pluck('code')->all();
                $this->audit->log('assign', $locked, null, [
                    'crates' => count($assigned),
                    'codes' => $codes,
                ], 'Asignó '.count($assigned).' cajón/es a la carga '.$locked->number);

                $this->syncPallets($locked, array_keys($palletIds), PalletStatus::WithProduct, PalletStatus::Reserved, true,
                    'Pallet completo asignado a la carga '.$locked->number);
                $this->recalculateTotals($locked);
            }

            return ['assigned' => count($assigned), 'assigned_ids' => $assigned, 'rejected' => $rejected];
        });

        $load->refresh();

        return $result;
    }

    /** Asigna todos los cajones disponibles de un pallet. */
    public function assignPallet(Load $load, Pallet $pallet, User $by): array
    {
        $ids = Crate::query()->where('pallet_id', $pallet->id)->whereNull('current_load_id')
            ->whereIn('status', array_map(fn ($s) => $s->value, CrateStatus::assignable()))
            ->orderBy('id')->limit(self::MAX_PER_REQUEST)->pluck('id')->all();

        if ($ids === []) {
            throw new BusinessException("El pallet {$pallet->code} no tiene cajones disponibles para asignar.");
        }

        return $this->assignCrates($load, $ids, $by);
    }

    /**
     * Resuelve códigos escaneados (código o código de barras) a IDs.
     *
     * @return array{ids: list<int>, rejected: list<array{crate_id: null, code: string, reason: string}>}
     */
    public function resolveCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($c) => trim((string) $c), $codes))));
        if (count($codes) > self::MAX_PER_REQUEST) {
            throw new BusinessException('Demasiados códigos en una sola operación.');
        }

        $found = Crate::query()->where(fn ($q) => $q->whereIn('code', $codes)->orWhereIn('barcode', $codes))
            ->get(['id', 'code', 'barcode']);

        $ids = [];
        $rejected = [];
        foreach ($codes as $code) {
            $crate = $found->first(fn ($c) => $c->code === $code || $c->barcode === $code);
            if ($crate) {
                $ids[] = $crate->id;
            } else {
                $rejected[] = ['crate_id' => null, 'code' => $code, 'reason' => 'Código inexistente.'];
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'rejected' => $rejected];
    }

    /**
     * Quita cajones de una carga en armado.
     *
     * @return array{removed: int, rejected: list<array{crate_id: int, code: string|null, reason: string}>}
     */
    public function removeCrates(Load $load, array $crateIds, User $by): array
    {
        $ids = $this->normalizeIds($crateIds);
        if (count($ids) > self::MAX_PER_REQUEST) {
            throw new BusinessException('Se pueden quitar como máximo '.num(self::MAX_PER_REQUEST).' cajones por operación.');
        }

        $result = DB::transaction(function () use ($load, $ids, $by) {
            $locked = $this->lockDraft($load, 'La carga no está en armado: no se pueden quitar cajones.');
            $released = $this->releaseCrates($locked, $ids, $by, 'Quitado de la carga '.$locked->number);

            if ($released['removed'] > 0) {
                $this->audit->log('unassign', $locked, ['crates' => $released['removed'], 'codes' => $released['codes']], null,
                    'Quitó '.$released['removed'].' cajón/es de la carga '.$locked->number);
                $this->recalculateTotals($locked);
            }

            return ['removed' => $released['removed'], 'rejected' => $released['rejected']];
        });

        $load->refresh();

        return $result;
    }

    // ------------------------------------------------------------------
    // Ciclo de vida
    // ------------------------------------------------------------------

    /**
     * Cierra la carga (Draft → Closed). Si $expectedVersion se informa (versión revisada en la
     * pantalla de resumen) y la carga cambió desde entonces, se rechaza para que se revise de nuevo.
     */
    public function close(Load $load, User $by, ?int $expectedVersion = null): Load
    {
        DB::transaction(function () use ($load, $by, $expectedVersion) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Draft) {
                throw new InvalidTransitionException($locked->status->label(), LoadStatus::Closed->label());
            }
            if ($expectedVersion !== null && $expectedVersion !== (int) $locked->version) {
                throw new ConcurrencyException('La carga cambió desde que revisaste el resumen (otro usuario agregó o quitó cajones). Revisalo nuevamente.');
            }
            if (! Crate::query()->where('current_load_id', $locked->id)->exists()) {
                throw new BusinessException('No se puede cerrar una carga sin cajones.');
            }

            // Transición atómica sobre la instancia recibida: si otro usuario la cerró o la modificó
            // (versión distinta), falla con ConcurrencyException y no se aplica ningún efecto.
            $this->transitions->transition($load, LoadStatus::Closed, 'Carga cerrada', [
                'closed_at' => now(),
                'closed_by' => $by->id,
            ]);

            $this->bulkCrateTransition($load, CrateStatus::Reserved, CrateStatus::Loaded, 'Carga '.$load->number.' cerrada', $by, true);
            $this->syncPallets($load, $this->palletIdsOf($load), PalletStatus::Reserved, PalletStatus::Loaded, true,
                'Carga '.$load->number.' cerrada');
        });

        LoadClosed::dispatch($load);

        return $load;
    }

    /** Reabre una carga cerrada (Closed → Draft). Requiere permiso loads.reopen y motivo. */
    public function reopen(Load $load, string $reason, User $by): Load
    {
        Gate::forUser($by)->authorize('loads.reopen');
        if (trim($reason) === '') {
            throw new BusinessException('Indicá el motivo de la reapertura.');
        }

        DB::transaction(function () use ($load, $reason, $by) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Closed) {
                throw new InvalidTransitionException($locked->status->label(), LoadStatus::Draft->label());
            }
            $remito = Remito::query()->where('active_load_id', $locked->id)->first();
            if ($remito) {
                throw new BusinessException("La carga tiene el remito {$remito->number} emitido. Anulalo antes de reabrirla.");
            }
            if (Invoice::query()->where('load_id', $locked->id)->whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Authorized->value])->exists()) {
                throw new BusinessException('La carga tiene comprobantes enviados o autorizados: no se puede reabrir.');
            }

            $this->transitions->transition($locked, LoadStatus::Draft, 'Reapertura: '.$reason, [
                'closed_at' => null,
                'closed_by' => null,
            ]);
            $this->bulkCrateTransition($locked, CrateStatus::Loaded, CrateStatus::Reserved, 'Carga '.$locked->number.' reabierta', $by, true);
            $this->syncPallets($locked, $this->palletIdsOf($locked), PalletStatus::Loaded, PalletStatus::Reserved, true,
                'Carga '.$locked->number.' reabierta');

            // El checklist de despacho deja de ser válido: el contenido puede cambiar.
            DispatchCheck::query()->where('load_id', $locked->id)->update([
                'checked' => false, 'user_id' => $by->id, 'checked_at' => now(), 'notes' => 'Reiniciado al reabrir la carga',
            ]);

            $this->audit->log('reopen', $locked, ['status' => LoadStatus::Closed->value], ['status' => LoadStatus::Draft->value],
                'Reabrió la carga '.$locked->number, $reason);
        });

        $load->refresh();

        return $load;
    }

    /** Cancela una carga en armado liberando todos sus cajones. */
    public function cancel(Load $load, string $reason, User $by): Load
    {
        if (trim($reason) === '') {
            throw new BusinessException('Indicá el motivo de la cancelación.');
        }

        DB::transaction(function () use ($load, $reason, $by) {
            $locked = $this->lockDraft($load, 'Sólo se pueden cancelar cargas en armado.');
            $this->transitions->transition($locked, LoadStatus::Cancelled, 'Cancelada: '.$reason);

            $ids = Crate::query()->where('current_load_id', $locked->id)->pluck('id')->all();
            $released = $this->releaseCrates($locked, $ids, $by, 'Liberado por cancelación de la carga '.$locked->number);
            $this->recalculateTotals($locked, false);

            $this->audit->log('cancel', $locked, ['status' => LoadStatus::Draft->value], [
                'status' => LoadStatus::Cancelled->value, 'released_crates' => $released['removed'],
            ], 'Canceló la carga '.$locked->number, $reason);
        });

        $load->refresh();

        return $load;
    }

    /** Marca (o desmarca) un ítem del checklist de despacho registrando quién y cuándo. */
    public function checkItem(Load $load, string $item, bool $checked, User $by, ?string $notes = null): DispatchCheck
    {
        if (! array_key_exists($item, DispatchCheck::ITEMS)) {
            throw new BusinessException('Ítem de control inválido.');
        }

        return DB::transaction(function () use ($load, $item, $checked, $by, $notes) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Closed) {
                throw new BusinessException('El checklist se completa con la carga cerrada y antes de despachar.');
            }
            if ($checked) {
                $this->assertCheckPrerequisite($locked, $item);
            }

            return DispatchCheck::query()->updateOrCreate(
                ['load_id' => $locked->id, 'item' => $item],
                ['checked' => $checked, 'user_id' => $by->id, 'checked_at' => now(), 'notes' => $notes],
            );
        });
    }

    /** @return list<string> claves de ítems pendientes del checklist */
    public function pendingChecks(Load $load): array
    {
        $done = DispatchCheck::query()->where('load_id', $load->id)->where('checked', true)->pluck('item')->all();

        return array_values(array_diff(array_keys(DispatchCheck::ITEMS), $done));
    }

    /** Despacha la carga: checklist completo + remito emitido. Closed → Dispatched. */
    public function dispatch(Load $load, User $by): Load
    {
        DB::transaction(function () use ($load, $by) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Closed) {
                throw new InvalidTransitionException($locked->status->label(), LoadStatus::Dispatched->label());
            }

            $pending = $this->pendingChecks($locked);
            if ($pending !== []) {
                $labels = array_map(fn ($k) => DispatchCheck::ITEMS[$k], $pending);
                throw new BusinessException('Faltan controles del checklist: '.implode(', ', $labels).'.');
            }

            $remito = Remito::query()->where('active_load_id', $locked->id)->first();
            if (! $remito || $remito->status !== RemitoStatus::Issued) {
                throw new BusinessException('Debe emitirse el remito antes de despachar la carga.');
            }

            $plate = $locked->truck()->value('plate');
            if (! $plate) {
                throw new BusinessException('La carga no tiene camión asignado.');
            }
            $label = 'Camión '.$plate;
            $now = now();

            $this->transitions->transition($locked, LoadStatus::Dispatched, 'Despachada en '.$label, [
                'dispatched_at' => $now,
                'dispatched_by' => $by->id,
            ]);
            $this->bulkCrateTransition($locked, CrateStatus::Loaded, CrateStatus::Dispatched, 'Despachado en '.$label, $by, true);

            // Movimientos de ubicación: el cajón/pallet sale del galpón hacia el camión.
            DB::table('crates')->where('current_load_id', $locked->id)->whereNull('deleted_at')
                ->select(['id', 'location_id'])->orderBy('id')
                ->chunk(500, function ($rows) use ($locked, $label, $by, $now) {
                    DB::table('location_movements')->insert($rows->map(fn ($r) => [
                        'movable_type' => 'crate', 'movable_id' => $r->id, 'from_location_id' => $r->location_id,
                        'to_location_id' => null, 'to_label' => $label, 'user_id' => $by->id,
                        'notes' => 'Despacho carga '.$locked->number, 'moved_at' => $now,
                    ])->all());
                });
            DB::table('crates')->where('current_load_id', $locked->id)->update(['location_id' => null]);

            $palletIds = $this->palletIdsOf($locked);
            $dispatchedPallets = $this->syncPallets($locked, $palletIds, PalletStatus::Loaded, PalletStatus::Dispatched, true,
                'Despachado en '.$label);
            foreach ($dispatchedPallets as $pallet) {
                DB::table('location_movements')->insert([
                    'movable_type' => 'pallet', 'movable_id' => $pallet->id, 'from_location_id' => $pallet->location_id,
                    'to_location_id' => null, 'to_label' => $label, 'user_id' => $by->id,
                    'notes' => 'Despacho carga '.$locked->number, 'moved_at' => $now,
                ]);
                DB::table('pallets')->where('id', $pallet->id)->update(['location_id' => null]);
            }

            // Si la carga ya estaba facturada (CAE antes del despacho), los cajones pasan a Facturado.
            if (Invoice::query()->where('load_id', $locked->id)->where('status', InvoiceStatus::Authorized->value)
                ->whereNotIn('voucher_type', array_keys(Invoice::CREDIT_NOTE_FOR))->exists()) {
                $this->bulkCrateTransition($locked, CrateStatus::Dispatched, CrateStatus::Invoiced, 'Carga facturada', $by, false);
            }
        });

        $load->refresh();
        LoadDispatched::dispatch($load);

        return $load;
    }

    /** Entrega confirmada (desde el remito): Dispatched → Delivered. */
    public function markDelivered(Load $load, ?string $notes = null): Load
    {
        return DB::transaction(function () use ($load, $notes) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Dispatched) {
                throw new BusinessException('La carga todavía no fue despachada: no se puede registrar la entrega.');
            }
            $this->transitions->transition($locked, LoadStatus::Delivered, $notes ?? 'Entrega registrada');
            $load->refresh();

            return $load;
        });
    }

    /** Pasa a Facturado los cajones despachados de la carga (al autorizarse el comprobante). */
    public function markInvoiced(Load $load, User|null $by = null): int
    {
        return DB::transaction(fn () => $this->bulkCrateTransition($load, CrateStatus::Dispatched, CrateStatus::Invoiced,
            'Carga facturada', $by, false));
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Cajones DISPONIBLES para asignar (sin carga, procesados/aprobados), con filtros. Orden FIFO.
     */
    public function availableQuery(Load $load, array $f): Builder
    {
        $statuses = array_map(fn ($s) => $s->value, CrateStatus::assignable());
        if (! empty($f['status']) && in_array($f['status'], $statuses, true)) {
            $statuses = [$f['status']];
        }

        return Crate::query()
            ->whereNull('current_load_id')
            ->whereIn('status', $statuses)
            ->where('warehouse_id', $load->warehouse_id)
            ->when($f['variety_id'] ?? null, fn ($q, $v) => $q->where('variety_id', $v))
            ->when($f['size_id'] ?? null, fn ($q, $v) => $q->where('size_id', $v))
            ->when($f['lot_id'] ?? null, fn ($q, $v) => $q->where('lot_id', $v))
            ->when($f['producer_id'] ?? null, fn ($q, $v) => $q->where('producer_id', $v))
            ->when($f['owner_id'] ?? null, fn ($q, $v) => $q->where('owner_id', $v))
            ->when($f['pallet_id'] ?? null, fn ($q, $v) => $q->where('pallet_id', $v))
            ->when(isset($f['weight_min']) && $f['weight_min'] !== '', fn ($q) => $q->where('weight', '>=', (float) $f['weight_min']))
            ->when(isset($f['weight_max']) && $f['weight_max'] !== '', fn ($q) => $q->where('weight', '<=', (float) $f['weight_max']))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->where('processed_at', '>=', $v.' 00:00:00'))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->where('processed_at', '<=', $v.' 23:59:59'))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('code', 'like', $v.'%')->orWhere('barcode', 'like', $v.'%')))
            ->orderByRaw('COALESCE(processed_at, created_at)')
            ->orderBy('id');
    }

    /** IDs de los primeros N cajones disponibles (FIFO) que cumplen los filtros. */
    public function takeAvailable(Load $load, array $filters, int $limit): array
    {
        $limit = max(1, min($limit, self::MAX_PER_REQUEST));

        return $this->availableQuery($load, $filters)->limit($limit)->pluck('id')->all();
    }

    /** Resumen para la pantalla de cierre / remito. */
    public function summary(Load $load): array
    {
        $base = fn () => DB::table('crates')->where('crates.current_load_id', $load->id)->whereNull('crates.deleted_at');

        $totals = $base()->selectRaw('COUNT(*) as crates, COALESCE(SUM(crates.weight), 0) as kg, COUNT(DISTINCT crates.pallet_id) as pallets')->first();

        $group = function (string $table, string $fk, string $nameColumn, ?string $order = null) use ($base) {
            $q = $base()->leftJoin($table, $table.'.id', '=', 'crates.'.$fk)
                ->groupBy('crates.'.$fk, $table.'.'.$nameColumn)
                ->selectRaw("crates.{$fk} as id, {$table}.{$nameColumn} as name, COUNT(*) as crates, COALESCE(SUM(crates.weight), 0) as kg");
            if ($order) {
                $q->groupBy($table.'.'.$order)->orderBy($table.'.'.$order);
            } else {
                $q->orderByDesc('crates');
            }

            return $q->get()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name ?? 'Sin asignar', 'crates' => (int) $r->crates, 'kg' => round((float) $r->kg, 2)])->all();
        };

        return [
            'crates' => (int) $totals->crates,
            'kg' => round((float) $totals->kg, 2),
            'pallets' => (int) $totals->pallets,
            'avg_kg' => $totals->crates > 0 ? round((float) $totals->kg / (int) $totals->crates, 2) : 0,
            'by_variety' => $group('varieties', 'variety_id', 'name'),
            'by_size' => $group('sizes', 'size_id', 'name', 'sort'),
            'by_owner' => $group('owners', 'owner_id', 'name'),
            'by_producer' => $group('producers', 'producer_id', 'name'),
            'by_variety_size' => $this->byVarietySize($load),
        ];
    }

    /** Detalle por variedad + tamaño (base de los ítems de remito y factura). */
    public function byVarietySize(Load $load): array
    {
        return DB::table('crates')
            ->leftJoin('varieties', 'varieties.id', '=', 'crates.variety_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'crates.size_id')
            ->where('crates.current_load_id', $load->id)->whereNull('crates.deleted_at')
            ->groupBy('crates.variety_id', 'crates.size_id', 'varieties.name', 'sizes.name', 'sizes.sort')
            ->orderBy('varieties.name')->orderBy('sizes.sort')
            ->selectRaw('crates.variety_id, crates.size_id, varieties.name as variety, sizes.name as size, COUNT(*) as crates, COALESCE(SUM(crates.weight), 0) as kg')
            ->get()
            ->map(fn ($r) => [
                'variety_id' => $r->variety_id, 'size_id' => $r->size_id,
                'variety' => $r->variety ?? 'Sin variedad', 'size' => $r->size ?? 'Sin tamaño',
                'crates' => (int) $r->crates, 'kg' => round((float) $r->kg, 2),
            ])->all();
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** Bloquea la carga (FOR UPDATE) y exige que esté en armado. */
    private function lockDraft(Load $load, string $message): Load
    {
        $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->first();
        if (! $locked) {
            throw new BusinessException('La carga no existe.');
        }
        if ($locked->status !== LoadStatus::Draft) {
            throw new BusinessException($message);
        }

        return $locked;
    }

    private function rejectionReason(?Crate $crate, Load $load, array $loadNumbers, array $assignable): ?string
    {
        if (! $crate || $crate->deleted_at !== null) {
            return 'El cajón no existe o fue eliminado.';
        }
        if ((int) $crate->current_load_id === (int) $load->id) {
            return 'Ya está en esta carga.';
        }
        if ($crate->current_load_id !== null) {
            $number = $loadNumbers[$crate->current_load_id] ?? '#'.$crate->current_load_id;

            return "Ya asignado a otra carga ({$number}).";
        }
        if (! in_array($crate->status->value, $assignable, true)) {
            return 'Estado no válido: '.$crate->status->label().'.';
        }
        if ($crate->warehouse_id !== null && (int) $crate->warehouse_id !== (int) $load->warehouse_id) {
            return 'Pertenece a otro galpón.';
        }

        return null;
    }

    /** Motivo cuando el UPDATE condicional no afectó filas (otro usuario se adelantó). */
    private function raceReason(int $id): string
    {
        $row = DB::table('crates')->where('id', $id)->first(['current_load_id', 'status']);
        if ($row && $row->current_load_id) {
            $number = DB::table('loads')->where('id', $row->current_load_id)->value('number');

            return 'Otro usuario lo asignó recién a otra carga'.($number ? " ({$number})" : '').'.';
        }

        return 'Su estado cambió mientras se asignaba. Actualizá e intentá de nuevo.';
    }

    /**
     * Inserta los registros load_crates. Si el índice único de active_crate_id rechaza alguno,
     * se reintenta fila por fila y los cajones en conflicto se revierten y se informan como rechazados.
     *
     * @param  array<int, Crate>  $taken  (se modifica: se quitan los revertidos)
     */
    private function insertAssignments(Load $load, array &$taken, User $by, $now, array &$rejected): void
    {
        if ($taken === []) {
            return;
        }

        $row = fn (int $id) => [
            'load_id' => $load->id, 'crate_id' => $id, 'active_crate_id' => $id,
            'added_by' => $by->id, 'added_at' => $now,
        ];

        try {
            DB::transaction(fn () => LoadCrate::query()->insert(array_map($row, array_keys($taken))));

            return;
        } catch (UniqueConstraintViolationException) {
            // Se resuelve fila por fila (cada una en su propio savepoint).
        }

        foreach (array_keys($taken) as $id) {
            try {
                DB::transaction(fn () => LoadCrate::query()->insert($row($id)));
            } catch (UniqueConstraintViolationException) {
                DB::table('crates')->where('id', $id)->where('current_load_id', $load->id)->update([
                    'current_load_id' => null,
                    'status' => $taken[$id]->status->value,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => $now,
                ]);
                $rejected[] = ['crate_id' => $id, 'code' => $taken[$id]->code, 'reason' => 'Ya tiene una asignación activa en otra carga.'];
                unset($taken[$id]);
            }
        }
    }

    /**
     * Libera cajones de la carga (vuelven a Aprobado o Procesado según su calidad).
     *
     * @return array{removed: int, codes: list<string>, rejected: list<array>}
     */
    private function releaseCrates(Load $load, array $ids, User $by, string $notes): array
    {
        $removed = [];
        $codes = [];
        $rejected = [];
        $history = [];
        $palletIds = [];
        $now = now();

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $crates = Crate::query()->whereIn('id', $chunk)->where('current_load_id', $load->id)
                ->get(['id', 'code', 'status', 'quality_status', 'pallet_id'])->keyBy('id');

            foreach ($chunk as $id) {
                $crate = $crates->get($id);
                if (! $crate) {
                    $rejected[] = ['crate_id' => $id, 'code' => null, 'reason' => 'No está en esta carga.'];
                    continue;
                }
                $to = $crate->quality_status === 'approved' ? CrateStatus::Approved : CrateStatus::Processed;
                $affected = DB::table('crates')->where('id', $id)
                    ->where('current_load_id', $load->id)
                    ->where('status', CrateStatus::Reserved->value)
                    ->update([
                        'current_load_id' => null,
                        'status' => $to->value,
                        'version' => DB::raw('version + 1'),
                        'updated_at' => $now,
                    ]);
                if ($affected !== 1) {
                    $rejected[] = ['crate_id' => $id, 'code' => $crate->code, 'reason' => 'No se pudo quitar: estado '.$crate->status->label().'.'];
                    continue;
                }
                $removed[] = $id;
                $codes[] = $crate->code;
                if ($crate->pallet_id) {
                    $palletIds[$crate->pallet_id] = true;
                }
                $history[] = $this->historyRow($id, CrateStatus::Reserved->value, $to->value, $by, $now, $notes);
            }
        }

        foreach (array_chunk($removed, 500) as $chunk) {
            LoadCrate::query()->where('load_id', $load->id)->whereIn('crate_id', $chunk)->whereNull('removed_at')
                ->update(['removed_at' => $now, 'removed_by' => $by->id, 'active_crate_id' => null]);
        }
        $this->insertHistory($history);

        // Un pallet que estaba completo en la carga deja de estarlo.
        $this->syncPallets($load, array_keys($palletIds), PalletStatus::Reserved, PalletStatus::WithProduct, false, $notes);

        return ['removed' => count($removed), 'codes' => array_slice($codes, 0, 50), 'rejected' => $rejected];
    }

    /**
     * Cambio de estado masivo de los cajones de una carga (con historial por cajón).
     * Si $strict, todos los cajones de la carga deben estar en $from.
     */
    private function bulkCrateTransition(Load $load, CrateStatus $from, CrateStatus $to, string $notes, ?User $by, bool $strict): int
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidTransitionException($from->label(), $to->label());
        }

        if ($strict) {
            $unexpected = Crate::query()->where('current_load_id', $load->id)->where('status', '!=', $from->value)->count();
            if ($unexpected > 0) {
                throw new BusinessException("Hay {$unexpected} cajón/es de la carga con un estado inesperado (deberían estar «{$from->label()}»). Revisá la carga.");
            }
        }

        $ids = Crate::query()->where('current_load_id', $load->id)->where('status', $from->value)->pluck('id')->all();
        $now = now();
        foreach (array_chunk($ids, 500) as $chunk) {
            $affected = DB::table('crates')->whereIn('id', $chunk)
                ->where('current_load_id', $load->id)
                ->where('status', $from->value)
                ->update(['status' => $to->value, 'version' => DB::raw('version + 1'), 'updated_at' => $now]);
            if ($affected !== count($chunk)) {
                throw new ConcurrencyException;
            }
            $this->insertHistory(array_map(fn ($id) => $this->historyRow($id, $from->value, $to->value, $by, $now, $notes), $chunk));
        }

        return count($ids);
    }

    /**
     * Cambia el estado de los pallets indicados. Con $requireFull sólo los que tienen TODOS sus
     * cajones (no anulados) en la carga; sin él, sólo los que YA NO están completos.
     *
     * @return list<Pallet> pallets efectivamente cambiados
     */
    private function syncPallets(Load $load, array $palletIds, PalletStatus $from, PalletStatus $to, bool $requireFull, string $notes): array
    {
        if ($palletIds === []) {
            return [];
        }

        $stats = DB::table('crates')->whereIn('pallet_id', $palletIds)->whereNull('deleted_at')
            ->where('status', '!=', CrateStatus::Voided->value)
            ->groupBy('pallet_id')
            ->selectRaw('pallet_id, COUNT(*) as total, SUM(CASE WHEN current_load_id = ? THEN 1 ELSE 0 END) as in_load', [$load->id])
            ->get()->keyBy('pallet_id');

        $changed = [];
        $pallets = Pallet::query()->whereIn('id', $palletIds)->where('status', $from->value)->get();
        foreach ($pallets as $pallet) {
            $s = $stats->get($pallet->id);
            $full = $s && (int) $s->total > 0 && (int) $s->total === (int) $s->in_load;
            if ($full !== $requireFull || ! $pallet->status->canTransitionTo($to)) {
                continue;
            }
            try {
                $this->transitions->transition($pallet, $to, $notes);
                $changed[] = $pallet;
            } catch (ConcurrencyException) {
                // Otro usuario modificó el pallet: su estado se reevaluará en la próxima operación.
            }
        }

        return $changed;
    }

    /** @return list<int> */
    private function palletIdsOf(Load $load): array
    {
        return DB::table('crates')->where('current_load_id', $load->id)->whereNotNull('pallet_id')
            ->distinct()->pluck('pallet_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Recalcula totales con una consulta agregada (nunca sumando en PHP). */
    private function recalculateTotals(Load $load, bool $bumpVersion = true): void
    {
        $agg = DB::table('crates')->where('current_load_id', $load->id)->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(weight), 0) as kg')->first();

        $values = ['total_crates' => (int) $agg->c, 'total_kg' => round((float) $agg->kg, 2), 'updated_at' => now()];
        if ($bumpVersion) {
            // Cambió el contenido: un cierre basado en un resumen anterior debe fallar.
            $values['version'] = DB::raw('version + 1');
        }
        DB::table('loads')->where('id', $load->id)->update($values);
    }

    private function assertCheckPrerequisite(Load $load, string $item): void
    {
        $error = match ($item) {
            'truck', 'plate' => $load->truck_id ? null : 'La carga no tiene camión asignado.',
            'driver' => $load->driver_id ? null : 'La carga no tiene chofer asignado.',
            'destination' => $load->destination_id ? null : 'La carga no tiene destino asignado.',
            'quantity', 'weight' => $load->total_crates > 0 ? null : 'La carga no tiene cajones.',
            'remito' => Remito::query()->where('active_load_id', $load->id)->where('status', RemitoStatus::Issued->value)->exists()
                ? null : 'Todavía no se emitió el remito de esta carga.',
            default => null,
        };
        if ($error) {
            throw new BusinessException($error);
        }
    }

    private function historyRow(int $id, string $from, string $to, ?User $by, $now, string $notes): array
    {
        return [
            'stateful_type' => 'crate', 'stateful_id' => $id, 'from_state' => $from, 'to_state' => $to,
            'user_id' => $by?->id ?? auth()->id(), 'notes' => mb_substr($notes, 0, 250), 'created_at' => $now,
        ];
    }

    private function insertHistory(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('state_histories')->insert($chunk);
        }
    }

    /** @return list<int> */
    private function normalizeIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $clean[(int) $id] = true;
            }
        }

        return array_keys($clean);
    }
}
