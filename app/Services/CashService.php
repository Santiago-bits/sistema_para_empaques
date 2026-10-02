<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use App\Support\CurrentWarehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Caja de efectivo del galpón: apertura con saldo anterior, ingresos, egresos y cierre con arqueo.
 * Una sola caja abierta por galpón (índice único open_warehouse_id). Los movimientos no se editan:
 * se anulan con motivo mientras la caja está abierta.
 */
class CashService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function current(): ?CashSession
    {
        return CashSession::query()->where('open_warehouse_id', CurrentWarehouse::id())->first();
    }

    public function lastClosed(): ?CashSession
    {
        return CashSession::query()->where('warehouse_id', CurrentWarehouse::id())->whereNotNull('closed_at')
            ->latest('closed_at')->latest('id')->first();
    }

    /** Saldo anterior sugerido: lo contado en el último cierre. */
    public function suggestedOpening(): float
    {
        return (float) ($this->lastClosed()?->counted_balance ?? 0);
    }

    public function open(float $openingBalance, ?string $notes, User $by): CashSession
    {
        $warehouse = CurrentWarehouse::id();
        try {
            $session = DB::transaction(fn () => CashSession::query()->create([
                'warehouse_id' => $warehouse,
                'open_warehouse_id' => $warehouse,
                'opened_at' => now(),
                'opened_by' => $by->id,
                'opening_balance' => round($openingBalance, 2),
                'notes' => $notes,
            ]));
        } catch (QueryException) {
            throw new BusinessException('La caja ya está abierta.');
        }

        $previous = $this->lastClosed();
        if ($previous && abs((float) $previous->counted_balance - $session->opening_balance) > 0.004) {
            $this->audit->log('cash_open', $session, ['expected' => (float) $previous->counted_balance], ['opening' => (float) $session->opening_balance],
                'Caja abierta con saldo distinto al último cierre', $notes);
        }

        return $session;
    }

    /** @param  array{direction: string, category: string, description: string, amount: float, account_movement_id?: int}  $data */
    public function addMovement(array $data, User $by): CashMovement
    {
        return DB::transaction(function () use ($data, $by) {
            $session = CashSession::query()->where('open_warehouse_id', CurrentWarehouse::id())->lockForUpdate()->first();
            if (! $session) {
                throw new BusinessException('La caja está cerrada. Abrila para registrar movimientos en efectivo.');
            }
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw new BusinessException('El importe debe ser mayor a cero.');
            }
            if ($data['direction'] === 'out' && $amount - $session->totals()['balance'] > 0.004) {
                throw new BusinessException('No hay suficiente efectivo en caja: saldo '.money($session->totals()['balance']).'.');
            }

            return CashMovement::query()->create([
                'cash_session_id' => $session->id,
                'moved_at' => now(),
                'direction' => $data['direction'],
                'category' => $data['category'],
                'description' => mb_substr($data['description'], 0, 255),
                'amount' => $amount,
                'account_movement_id' => $data['account_movement_id'] ?? null,
                'user_id' => $by->id,
            ]);
        });
    }

    /**
     * Anula un movimiento de caja. Si viene de una cuenta corriente (cobro/pago en efectivo), por
     * defecto se anula también ese movimiento: la plata nunca entró/salió.
     */
    public function voidMovement(CashMovement $movement, string $reason, User $by, bool $cascade = true): void
    {
        DB::transaction(function () use ($movement, $reason, $by, $cascade) {
            $locked = CashMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($locked->voided_at) {
                throw new BusinessException('El movimiento ya estaba anulado.');
            }
            $session = CashSession::query()->whereKey($locked->cash_session_id)->lockForUpdate()->firstOrFail();
            if (! $session->isOpen()) {
                throw new BusinessException('La caja de ese movimiento ya se cerró: registrá un movimiento inverso en la caja actual.');
            }
            if ($locked->direction === 'in' && (float) $locked->amount - $session->totals()['balance'] > 0.004) {
                throw new BusinessException('No se puede anular: el saldo de la caja quedaría negativo.');
            }

            $locked->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => mb_substr($reason, 0, 255)])->save();
            $this->audit->log('void', $locked, null, ['voided' => true], 'Movimiento de caja anulado: '.$locked->description, $reason);

            if ($cascade && $locked->account_movement_id) {
                $account = \App\Models\AccountMovement::query()->whereKey($locked->account_movement_id)->lockForUpdate()->first();
                if ($account && ! $account->isVoided()) {
                    app(AccountService::class)->markVoided($account, $reason, $by);
                }
            }
        });
    }

    /** Cierre con arqueo: se compara lo contado con lo esperado y la diferencia queda registrada. */
    public function close(CashSession $session, float $counted, ?string $notes, User $by): CashSession
    {
        return DB::transaction(function () use ($session, $counted, $notes, $by) {
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpen()) {
                throw new BusinessException('La caja ya estaba cerrada.');
            }
            $expected = $locked->totals()['balance'];
            $difference = round($counted - $expected, 2);
            if (abs($difference) > 0.004 && trim((string) $notes) === '') {
                throw new BusinessException('Hay una diferencia de '.money($difference).' entre lo contado y lo esperado: indicá el motivo en observaciones.');
            }
            $locked->forceFill([
                'open_warehouse_id' => null,
                'closed_at' => now(),
                'closed_by' => $by->id,
                'expected_balance' => $expected,
                'counted_balance' => round($counted, 2),
                'difference' => $difference,
                'notes' => trim(implode("\n", array_filter([$locked->notes, $notes]))) ?: null,
            ])->save();
            $this->audit->log('cash_close', $locked, null, ['expected' => $expected, 'counted' => $counted, 'difference' => $difference],
                'Cierre de caja', $notes);

            return $locked;
        });
    }
}
