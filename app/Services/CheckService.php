<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\AccountMovement;
use App\Models\Check;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cheques de terceros (cartera, depósito, cobro, endoso, rechazo) y propios (emisión, débito, rechazo).
 * Cada cambio de estado es un UPDATE condicional sobre estado + versión: dos operadores no pueden
 * endosar o depositar el mismo cheque a la vez. Los efectos en cuentas corrientes son automáticos.
 */
class CheckService
{
    public function __construct(private readonly AccountService $accounts, private readonly AuditService $audit)
    {
    }

    /** Cheque suelto (sin titular de cuenta corriente): p. ej. uno que ya estaba en cartera. */
    public function create(array $data, User $by): Check
    {
        return $this->insert($data + ['status' => $data['kind'] === 'own' ? 'issued' : 'in_portfolio'], $by);
    }

    /** Cheque como medio de cobro (de terceros, del titular) o de pago (propio, al titular). */
    public function createForPayment(Model $holder, string $direction, array $data, float $amount, User $by): Check
    {
        $collection = $direction === 'collection';

        return $this->insert(array_merge($data, [
            'kind' => $collection ? 'third_party' : 'own',
            'amount' => $amount,
            'status' => $collection ? 'in_portfolio' : 'issued',
            'received_from_type' => $collection ? $holder->getMorphClass() : null,
            'received_from_id' => $collection ? $holder->getKey() : null,
            'delivered_to_type' => $collection ? null : $holder->getMorphClass(),
            'delivered_to_id' => $collection ? null : $holder->getKey(),
            'issuer_name' => $data['issuer_name'] ?? ($collection ? AccountMovement::holderLabel($holder) : setting('company.name')),
            'issuer_cuit' => $data['issuer_cuit'] ?? ($collection ? $holder->getAttribute('cuit') : setting('company.cuit')),
        ]), $by);
    }

    /** Endoso de un cheque en cartera para pagarle al titular. Debe llamarse dentro de una transacción. */
    public function endorseLocked(int $checkId, Model $holder, User $by): Check
    {
        $check = Check::query()->whereKey($checkId)->lockForUpdate()->first();
        if (! $check || $check->kind !== 'third_party' || $check->status !== 'in_portfolio') {
            throw new BusinessException('El cheque elegido ya no está en cartera (otro usuario pudo haberlo usado).');
        }
        $this->move($check, 'endorsed', [
            'delivered_to_type' => $holder->getMorphClass(), 'delivered_to_id' => $holder->getKey(),
        ], 'Endosado a '.AccountMovement::holderLabel($holder));

        return $check->refresh();
    }

    /**
     * Cambio de estado: deposited | cashed | paid | rejected | voided.
     * Rechazo: el librador vuelve a deber el importe (y si se había endosado, se lo volvemos a deber al endosatario).
     * Anulación: se anula el cobro/pago que había generado.
     */
    public function transition(Check $check, string $status, User $by, ?string $notes = null, ?string $date = null): Check
    {
        if ($status === 'endorsed') {
            throw new BusinessException('Para endosar un cheque registrá un pago con cheque desde la cuenta corriente del destinatario.');
        }
        if (in_array($status, ['rejected', 'voided'], true) && trim((string) $notes) === '') {
            throw new BusinessException('Indicá el motivo.');
        }

        return DB::transaction(function () use ($check, $status, $by, $notes, $date) {
            $locked = Check::query()->whereKey($check->id)->lockForUpdate()->firstOrFail();
            if (! $locked->canMoveTo($status)) {
                throw new BusinessException('Un cheque '.mb_strtolower($locked->statusLabel()).' no puede pasar a «'.Check::STATUSES[$status][0].'».');
            }
            $previous = $locked->status;
            $extra = ['status_date' => $date ? Carbon::parse($date) : today()];
            if (trim((string) $notes) !== '') {
                // El motivo queda a la vista en la ficha del cheque (además de la auditoría).
                $extra['notes'] = mb_substr(trim(($locked->notes ? $locked->notes."\n" : '').Check::STATUSES[$status][0].' ('.today()->format('d/m/Y').'): '.$notes), 0, 2000);
            }
            $this->move($locked, $status, $extra, $notes);
            $label = 'Cheque '.$locked->bank.' N° '.$locked->number;

            if ($status === 'rejected') {
                if ($locked->kind === 'third_party' && $locked->receivedFrom) {
                    $this->accounts->post($locked->receivedFrom, 'check_rejected', (float) $locked->amount, 0, $label.' rechazado',
                        ['source' => $locked, 'by' => $by, 'reference' => $notes]);
                }
                if ($locked->deliveredTo && ($locked->kind === 'own' || $previous === 'endorsed')) {
                    $this->accounts->post($locked->deliveredTo, 'check_rejected', 0, (float) $locked->amount, $label.' rechazado (se le vuelve a deber)',
                        ['source' => $locked, 'by' => $by, 'reference' => $notes]);
                }
            }

            if ($status === 'voided') {
                AccountMovement::query()->where('source_type', 'check')->where('source_id', $locked->id)->whereNull('voided_at')
                    ->lockForUpdate()->get()
                    ->each(fn (AccountMovement $m) => $this->accounts->markVoided($m, 'Cheque anulado: '.$notes, $by));
            }

            return $locked->refresh();
        });
    }

    private function insert(array $data, User $by): Check
    {
        if (round((float) ($data['amount'] ?? 0), 2) <= 0) {
            throw new BusinessException('El importe del cheque debe ser mayor a cero.');
        }

        try {
            $check = Check::query()->create([
                'kind' => $data['kind'],
                'electronic' => (bool) ($data['electronic'] ?? false),
                'bank' => trim((string) $data['bank']),
                'number' => trim((string) $data['number']),
                'issuer_name' => $data['issuer_name'] ?? null,
                'issuer_cuit' => isset($data['issuer_cuit']) ? preg_replace('/\D/', '', (string) $data['issuer_cuit']) ?: null : null,
                'amount' => round((float) $data['amount'], 2),
                'issued_on' => $data['issued_on'] ?? today(),
                'payment_date' => $data['payment_date'] ?? ($data['issued_on'] ?? today()),
                'status' => $data['status'],
                'received_from_type' => $data['received_from_type'] ?? null,
                'received_from_id' => $data['received_from_id'] ?? null,
                'delivered_to_type' => $data['delivered_to_type'] ?? null,
                'delivered_to_id' => $data['delivered_to_id'] ?? null,
                'status_date' => today(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);
        } catch (QueryException) {
            throw new BusinessException('Ya existe un cheque '.($data['kind'] === 'own' ? 'propio' : 'de terceros').' del banco «'.$data['bank'].'» con el número '.$data['number'].'.');
        }

        return $check;
    }

    private function move(Check $check, string $status, array $extra, ?string $notes): void
    {
        $from = $check->status;
        $updated = Check::query()->whereKey($check->id)->where('status', $from)->where('version', $check->version)
            ->update(array_merge($extra, ['status' => $status, 'version' => $check->version + 1, 'updated_at' => now()]));
        if ($updated !== 1) {
            throw new BusinessException('Otro usuario modificó el cheque al mismo tiempo. Actualizá la pantalla.');
        }
        $this->audit->log('check_'.$status, $check, ['status' => $from], ['status' => $status],
            'Cheque '.$check->bank.' N° '.$check->number.': '.Check::STATUSES[$from][0].' → '.Check::STATUSES[$status][0], $notes);
    }
}
