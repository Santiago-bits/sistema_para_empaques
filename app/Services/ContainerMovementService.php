<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AccountMovement;
use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Models\Load;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Envases (bines, jaulas, cajas) que se llevan o devuelven clientes, productores, camioneros, etc., y la
 * ficha de movimientos de cada uno: plata, envases, fruta que ingresó y cargas que salieron.
 *
 * Pago con envases: si el titular nos entrega envases como parte de pago, su cuenta corriente baja (haber);
 * si se los entregamos nosotros como parte de pago, sube (debe). Los dos movimientos quedan enlazados y se
 * anulan juntos.
 */
class ContainerMovementService
{
    public function __construct(private readonly AuditService $audit, private readonly AccountService $accounts)
    {
    }

    /**
     * @param  array{direction: string, quantity: int, container_type_id?: int|null, reason?: string,
     *     unit_price?: float|null, date?: string|null, description?: string|null}  $data
     */
    public function register(Model $holder, array $data, User $by): ContainerMovement
    {
        if (! array_key_exists($holder->getMorphClass(), ContainerMovement::HOLDERS)) {
            throw new \InvalidArgumentException('Titular de envases inválido.');
        }
        $direction = $data['direction'];
        $quantity = (int) $data['quantity'];
        $reason = $data['reason'] ?? 'loan';
        if (! array_key_exists($direction, ContainerMovement::DIRECTIONS) || ! array_key_exists($reason, ContainerMovement::REASONS)) {
            throw new BusinessException('Movimiento de envases inválido.');
        }
        if ($quantity < 1) {
            throw new BusinessException('La cantidad tiene que ser al menos 1.');
        }

        $unitPrice = isset($data['unit_price']) && $data['unit_price'] !== '' ? round((float) $data['unit_price'], 2) : null;
        $amount = $unitPrice !== null ? round($unitPrice * $quantity, 2) : null;
        if ($reason === 'payment') {
            if (! array_key_exists($holder->getMorphClass(), AccountMovement::HOLDERS)) {
                throw new BusinessException('Los camioneros no tienen cuenta corriente: registralo como préstamo o devolución.');
            }
            if (! module_enabled('treasury')) {
                throw new BusinessException('Para pagar con envases hay que activar Tesorería (cuentas corrientes).');
            }
            if (! $amount || $amount <= 0) {
                throw new BusinessException('Indicá cuánto vale cada envase para descontarlo de la cuenta.');
            }
        }

        $type = isset($data['container_type_id']) ? ContainerType::query()->find($data['container_type_id']) : null;
        $what = $this->describe($quantity, $type);
        $name = AccountMovement::holderLabel($holder);
        $description = trim((string) ($data['description'] ?? '')) ?: match ($direction) {
            'delivered' => "Se le entregaron {$what}",
            default => "Entregó {$what}",
        }.($reason === 'payment' ? ' como parte de pago' : '');

        return DB::transaction(function () use ($holder, $data, $by, $direction, $quantity, $reason, $unitPrice, $amount, $type, $description, $name) {
            $movement = ContainerMovement::query()->create([
                'holder_type' => $holder->getMorphClass(),
                'holder_id' => $holder->getKey(),
                'container_type_id' => $type?->id,
                'date' => Carbon::parse($data['date'] ?? today()),
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'description' => mb_substr($description, 0, 255),
                'user_id' => $by->id,
            ]);

            if ($reason === 'payment') {
                // Nos entrega envases → como un cobro (haber). Se los entregamos → como un pago (debe).
                $account = $this->accounts->post($holder, 'containers',
                    $direction === 'delivered' ? $amount : 0,
                    $direction === 'received' ? $amount : 0,
                    $description.' ('.money($unitPrice).' c/u)',
                    ['date' => $movement->date, 'method' => 'other', 'source' => $movement, 'by' => $by]);
                $movement->forceFill(['account_movement_id' => $account->id])->saveQuietly();
            }

            $this->audit->log('containers_'.$direction, $movement, null,
                ['quantity' => $quantity, 'reason' => $reason, 'amount' => $amount],
                $description.' · '.$name);

            return $movement;
        });
    }

    /** Anula el movimiento de envases y, si fue parte de pago, también el de la cuenta corriente. */
    public function void(ContainerMovement $movement, string $reason, User $by): void
    {
        DB::transaction(function () use ($movement, $reason, $by) {
            $locked = ContainerMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoided()) {
                throw new BusinessException('El movimiento ya estaba anulado.');
            }
            $account = $locked->account_movement_id
                ? AccountMovement::query()->whereKey($locked->account_movement_id)->whereNull('voided_at')->first()
                : null;
            if ($account) {
                // AccountService::void anula también este movimiento (por su origen).
                $this->accounts->void($account, $reason, $by);
            } else {
                $this->markVoided($locked->id, $reason, $by);
            }
        });
    }

    /** Marca anulado (lo usa también la cuenta corriente al anular un pago con envases). */
    public function markVoided(int $id, string $reason, User $by): void
    {
        $affected = ContainerMovement::query()->whereKey($id)->whereNull('voided_at')->update([
            'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => mb_substr($reason, 0, 255), 'updated_at' => now(),
        ]);
        if ($affected === 1) {
            $movement = ContainerMovement::query()->find($id);
            $this->audit->log('void', $movement, null, ['voided' => true], 'Movimiento de envases anulado: '.$movement->description, $reason);
        }
    }

    // ------------------------------------------------------------------ Consultas

    /**
     * Saldo de envases por tipo: + el titular tiene envases nuestros, − le debemos.
     *
     * @return Collection<int, object{name: string, balance: int}>
     */
    public function balances(Model $holder): Collection
    {
        $rows = ContainerMovement::query()->valid()->of($holder)
            ->selectRaw("container_type_id, SUM(CASE WHEN direction = 'delivered' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('container_type_id')->get();
        $names = ContainerType::withTrashed()->whereIn('id', $rows->pluck('container_type_id')->filter())->pluck('name', 'id');

        return $rows->map(fn ($r) => (object) [
            'name' => $r->container_type_id ? ($names[$r->container_type_id] ?? 'Envase') : 'Envases (sin tipo)',
            'balance' => (int) $r->balance,
        ])->filter(fn ($r) => $r->balance !== 0)->values();
    }

    /** Totales para la ficha: plata, envases, fruta ingresada y cargas. */
    public function summary(Model $holder): array
    {
        $type = $holder->getMorphClass();
        $lots = $this->lotsQuery($holder);
        $loads = $this->loadsQuery($holder);

        return [
            'balance' => array_key_exists($type, AccountMovement::HOLDERS) && module_enabled('treasury') ? $this->accounts->balance($holder) : null,
            'containers' => $this->balances($holder),
            'lots' => $lots ? (clone $lots)->selectRaw('COUNT(*) as count, COALESCE(SUM(bins), 0) as bins, COALESCE(SUM(kg_received), 0) as kg')->first() : null,
            'loads' => $loads ? (clone $loads)->selectRaw('COUNT(*) as count, COALESCE(SUM(total_crates), 0) as crates, COALESCE(SUM(total_kg), 0) as kg')->first() : null,
        ];
    }

    /**
     * Todos los movimientos del titular en una sola lista (más nuevos primero): plata, envases, ingresos de
     * fruta y cargas. `kind`: money | containers | lots | loads (o null para todos).
     */
    public function activity(Model $holder, ?string $kind = null, ?string $from = null, ?string $to = null): QueryBuilder
    {
        $type = $holder->getMorphClass();
        $parts = [];

        if ((! $kind || $kind === 'money') && array_key_exists($type, AccountMovement::HOLDERS) && module_enabled('treasury')) {
            $parts[] = AccountMovement::query()->where('holder_type', $type)->where('holder_id', $holder->getKey())->toBase()
                ->selectRaw("'money' as kind, id as ref_id, date, created_at as sort_at, type as ref, description, debit, credit, 0 as qty, 0 as crates, 0 as kg, CASE WHEN voided_at IS NULL THEN 0 ELSE 1 END as voided");
        }
        if (! $kind || $kind === 'containers') {
            $parts[] = ContainerMovement::query()->of($holder)->toBase()
                ->selectRaw("'containers' as kind, id as ref_id, date, created_at as sort_at, reason as ref, description, 0 as debit, 0 as credit, CASE WHEN direction = 'delivered' THEN quantity ELSE -quantity END as qty, 0 as crates, 0 as kg, CASE WHEN voided_at IS NULL THEN 0 ELSE 1 END as voided");
        }
        if ((! $kind || $kind === 'lots') && ($lots = $this->lotsQuery($holder))) {
            $parts[] = $lots->toBase()
                ->selectRaw("'lots' as kind, id as ref_id, date, created_at as sort_at, code as ref, notes as description, 0 as debit, 0 as credit, COALESCE(bins, 0) as qty, quantity as crates, COALESCE(kg_received, 0) as kg, 0 as voided");
        }
        if ((! $kind || $kind === 'loads') && ($loads = $this->loadsQuery($holder))) {
            $parts[] = $loads->toBase()
                ->selectRaw("'loads' as kind, id as ref_id, date, created_at as sort_at, number as ref, notes as description, 0 as debit, 0 as credit, 0 as qty, total_crates as crates, total_kg as kg, 0 as voided");
        }

        if ($parts === []) {
            return DB::query()->fromSub(ContainerMovement::query()->whereRaw('1 = 0')->toBase()
                ->selectRaw("'containers' as kind, id as ref_id, date, created_at as sort_at, reason as ref, description, 0 as debit, 0 as credit, 0 as qty, 0 as crates, 0 as kg, 0 as voided"), 'activity');
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'activity')
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')->orderByDesc('sort_at')->orderByDesc('ref_id');
    }

    /** Ingresos de fruta del titular (productor que la trajo o camionero que la transportó). */
    private function lotsQuery(Model $holder): ?\Illuminate\Database\Eloquent\Builder
    {
        if (! module_enabled('catalogs')) {
            return null;
        }
        $column = ['producer' => 'producer_id', 'driver' => 'driver_id'][$holder->getMorphClass()] ?? null;

        return $column ? Lot::query()->where($column, $holder->getKey())->where('status', '!=', 'voided') : null;
    }

    /** Cargas despachadas o en armado del titular (cliente, camionero o transportista). */
    private function loadsQuery(Model $holder): ?\Illuminate\Database\Eloquent\Builder
    {
        if (! module_enabled('loads')) {
            return null;
        }
        $column = ['client' => 'client_id', 'driver' => 'driver_id', 'transporter' => 'transporter_id'][$holder->getMorphClass()] ?? null;

        return $column ? Load::query()->where($column, $holder->getKey())->where('status', '!=', 'cancelled') : null;
    }

    private function describe(int $quantity, ?ContainerType $type): string
    {
        return $quantity.' '.($quantity === 1 ? 'envase' : 'envases').($type ? ' ('.$type->name.')' : '');
    }
}
