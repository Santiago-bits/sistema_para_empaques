<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LoadStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountMovement;
use App\Models\Check;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\Lot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas corrientes de clientes, productores, transportistas, proveedores y empleados.
 *
 * Convención (punto de vista del galpón): DEBE aumenta lo que el titular nos debe (factura, pago que
 * le hicimos, cheque suyo rechazado); HABER aumenta lo que le debemos (compra de fruta, flete, cobro
 * que nos hizo). Saldo = Σ debe − Σ haber → positivo: nos debe · negativo: le debemos.
 *
 * Los movimientos nunca se editan ni se borran: se anulan con motivo y quedan en auditoría.
 * Todo importe está en pesos (los comprobantes en dólares se convierten con su cotización).
 */
class AccountService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    // ------------------------------------------------------------------ Registro

    /**
     * Asienta un movimiento. Con `source` es idempotente: el mismo origen, tipo y titular se imputa
     * una sola vez (índice único); si ya existía, devuelve el existente.
     */
    public function post(Model $holder, string $type, float $debit, float $credit, string $description, array $extra = []): AccountMovement
    {
        $this->assertHolder($holder);
        if (! array_key_exists($type, AccountMovement::TYPES)) {
            throw new \InvalidArgumentException("Tipo de movimiento desconocido: {$type}");
        }
        $debit = round(max(0, $debit), 2);
        $credit = round(max(0, $credit), 2);
        if ($debit <= 0 && $credit <= 0) {
            throw new BusinessException('El importe debe ser mayor a cero.');
        }

        /** @var Model|null $source */
        $source = $extra['source'] ?? null;
        $attributes = [
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'date' => $extra['date'] ?? today(),
            'type' => $type,
            'description' => mb_substr($description, 0, 255),
            'debit' => $debit,
            'credit' => $credit,
            'payment_method' => $extra['method'] ?? null,
            'reference' => isset($extra['reference']) ? mb_substr((string) $extra['reference'], 0, 80) : null,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => ($extra['by'] ?? null)?->id ?? auth()->id(),
        ];

        if ($source) {
            $existing = $this->sourced($source, $type, $holder);
            if ($existing) {
                return $existing;
            }
            try {
                return DB::transaction(fn () => AccountMovement::query()->create($attributes));
            } catch (QueryException $e) {
                // Carrera: otro proceso lo imputó en el mismo instante → se devuelve ése.
                return $this->sourced($source, $type, $holder) ?? throw $e;
            }
        }

        return AccountMovement::query()->create($attributes);
    }

    /**
     * Cobro (el titular nos paga → haber) o pago (le pagamos → debe), en efectivo (pasa por la caja),
     * transferencia o cheque (de terceros recibido / propio emitido / endoso de uno en cartera).
     *
     * @param  array{direction: string, amount: float, method: string, date?: string, description?: string,
     *     reference?: string, check?: array, endorse_check_id?: int, cash_category?: string}  $data
     */
    public function registerPayment(Model $holder, array $data, User $by): AccountMovement
    {
        $this->assertHolder($holder);
        $direction = $data['direction'];
        // Al endosar un cheque el importe es el del cheque (se toma más abajo, con el cheque bloqueado).
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $method = $data['method'];
        if ($amount <= 0 && ! ($method === 'check' && ! empty($data['endorse_check_id']))) {
            throw new BusinessException('El importe debe ser mayor a cero.');
        }
        $date = Carbon::parse($data['date'] ?? today());
        $label = AccountMovement::holderLabel($holder);
        $type = $direction === 'collection' ? 'collection' : ($data['type'] ?? 'payment');
        $description = trim((string) ($data['description'] ?? '')) ?: (AccountMovement::TYPES[$type].' '.mb_strtolower(AccountMovement::METHODS[$method] ?? '').' · '.$label);

        return DB::transaction(function () use ($holder, $direction, $amount, $method, $date, $description, $data, $by, $type, $label) {
            $source = null;
            if ($method === 'check') {
                if ($direction === 'payment' && ! empty($data['endorse_check_id'])) {
                    $check = app(CheckService::class)->endorseLocked((int) $data['endorse_check_id'], $holder, $by);
                    $amount = (float) $check->amount;
                } else {
                    $check = app(CheckService::class)->createForPayment($holder, $direction, $data['check'] ?? [], $amount, $by);
                }
                $source = $check;
            }

            $movement = $this->post($holder, $type,
                $direction === 'collection' ? 0 : $amount,
                $direction === 'collection' ? $amount : 0,
                $description,
                ['date' => $date, 'method' => $method, 'reference' => $data['reference'] ?? ($source ? $source->bank.' N° '.$source->number : null),
                    'source' => $source, 'by' => $by]);

            if ($method === 'cash') {
                app(CashService::class)->addMovement([
                    'direction' => $direction === 'collection' ? 'in' : 'out',
                    'category' => $data['cash_category'] ?? self::cashCategory($holder, $direction),
                    'description' => $description,
                    'amount' => $amount,
                    'account_movement_id' => $movement->id,
                ], $by);
            }

            $this->audit->log($direction, $movement, null, ['amount' => $amount, 'method' => $method],
                AccountMovement::TYPES[$type].' de '.money($amount).' · '.$label);

            return $movement;
        });
    }

    /** Ajuste manual: cargo (nos debe más) o ajuste a favor del titular (le debemos más). Con motivo. */
    public function adjust(Model $holder, string $kind, float $amount, string $description, ?string $date, User $by): AccountMovement
    {
        $type = match ($kind) {
            'debit' => 'debit_note',
            'credit' => 'credit_adjustment',
            'opening_debit', 'opening_credit' => 'opening',
            'advance' => 'advance',
            default => throw new BusinessException('Tipo de ajuste inválido.'),
        };
        $isDebit = in_array($kind, ['debit', 'opening_debit', 'advance'], true);

        return $this->post($holder, $type, $isDebit ? $amount : 0, $isDebit ? 0 : $amount, $description,
            ['date' => $date ? Carbon::parse($date) : today(), 'by' => $by]);
    }

    /**
     * Anula un movimiento (y lo que depende de él: el movimiento de caja asociado; si era la liquidación
     * de un lote, también la tasa y el lote vuelve a quedar sin liquidar).
     */
    public function void(AccountMovement $movement, string $reason, User $by): void
    {
        DB::transaction(function () use ($movement, $reason, $by) {
            $locked = AccountMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoided()) {
                throw new BusinessException('El movimiento ya estaba anulado.');
            }
            if ($locked->source_type === 'check') {
                throw new BusinessException('Este movimiento corresponde a un cheque: anulá o rechazá el cheque desde Cheques.');
            }

            $related = collect([$locked]);
            if ($locked->source_type === 'lot' && in_array($locked->type, ['purchase', 'association_fee'], true)) {
                $related = AccountMovement::query()->where('source_type', 'lot')->where('source_id', $locked->source_id)
                    ->whereIn('type', ['purchase', 'association_fee'])->whereNull('voided_at')->lockForUpdate()->get();
                Lot::query()->whereKey($locked->source_id)->update(['settled_at' => null, 'settled_by' => null]);
            }

            foreach ($related as $item) {
                $this->markVoided($item, $reason, $by);
                // El movimiento de caja asociado se anula también (la caja debe estar abierta).
                $cash = \App\Models\CashMovement::query()->where('account_movement_id', $item->id)->whereNull('voided_at')->first();
                if ($cash) {
                    app(CashService::class)->voidMovement($cash, $reason, $by, false);
                }
            }
        });
    }

    /**
     * Corrige un movimiento cargado a mano (cobro, pago, ajuste, adelanto, saldo inicial) sin borrarlo:
     * fecha, detalle y referencia se corrigen en el lugar; si cambia el importe se anula y se vuelve a
     * asentar (y si era en efectivo, también se corrige la caja). Todo con motivo y en auditoría.
     */
    public function correct(AccountMovement $movement, array $data, string $reason, User $by): AccountMovement
    {
        return DB::transaction(function () use ($movement, $data, $reason, $by) {
            $locked = AccountMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoided()) {
                throw new BusinessException('El movimiento está anulado.');
            }
            $origin = ['check' => 'el cheque', 'invoice' => 'el comprobante', 'lot' => 'el lote', 'load' => 'la carga'][$locked->source_type] ?? null;
            if ($origin) {
                throw new BusinessException('Este movimiento se generó desde '.$origin.': corregilo ahí y la cuenta se actualiza sola.');
            }

            $amount = round((float) $data['amount'], 2);
            $current = (float) $locked->debit > 0 ? (float) $locked->debit : (float) $locked->credit;
            $meta = [
                'date' => Carbon::parse($data['date']),
                'description' => mb_substr((string) $data['description'], 0, 255),
                'reference' => isset($data['reference']) ? mb_substr((string) $data['reference'], 0, 80) : null,
            ];

            if (abs($amount - $current) < 0.005) {
                $this->audit->withReason($reason);
                try {
                    $locked->update($meta);
                } finally {
                    $this->audit->withReason(null);
                }

                return $locked;
            }

            $isDebit = (float) $locked->debit > 0;
            $cash = \App\Models\CashMovement::query()->where('account_movement_id', $locked->id)->whereNull('voided_at')->first();
            $this->markVoided($locked, 'Corrección: '.$reason, $by);
            $new = $this->post($locked->holder()->withTrashed()->firstOrFail(), $locked->type, $isDebit ? $amount : 0, $isDebit ? 0 : $amount,
                $meta['description'], ['date' => $meta['date'], 'method' => $locked->payment_method, 'reference' => $meta['reference'], 'by' => $by]);

            if ($cash) {
                app(CashService::class)->voidMovement($cash, 'Corrección: '.$reason, $by, false);
                app(CashService::class)->addMovement([
                    'direction' => $cash->direction, 'category' => $cash->category, 'description' => $meta['description'],
                    'amount' => $amount, 'account_movement_id' => $new->id,
                ], $by);
            }

            return $new;
        });
    }

    /** Marca anulado y libera el origen para que pueda volver a imputarse corregido. */
    public function markVoided(AccountMovement $movement, string $reason, User $by): void
    {
        $old = ['source_type' => $movement->source_type, 'source_id' => $movement->source_id];
        $movement->forceFill([
            'voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => mb_substr($reason, 0, 255),
            'source_type' => null, 'source_id' => null,
        ])->save();
        $this->audit->log('void', $movement, $old, ['voided' => true], 'Movimiento de cuenta corriente anulado: '.$movement->description, $reason);
    }

    // ------------------------------------------------------------------ Integraciones

    /** Factura → debe del cliente; nota de crédito → haber. En pesos según la cotización del comprobante. */
    public function postInvoice(Invoice $invoice): ?AccountMovement
    {
        if ($invoice->status !== InvoiceStatus::Authorized || ! $this->invoiceAffectsAccounts($invoice)) {
            return null;
        }
        $client = $invoice->client()->withTrashed()->first();
        if (! $client) {
            return null;
        }
        $amount = round((float) $invoice->total_amount * (float) ($invoice->exchange_rate ?: 1), 2);
        $credit = $invoice->isCreditNote();
        $label = $invoice->voucherLabel().' '.$invoice->formattedNumber()
            .($invoice->currency !== 'ARS' ? ' (US$ '.num($invoice->total_amount, 2).' a '.num($invoice->exchange_rate, 2).')' : '');

        return $this->post($client, $credit ? 'credit_note' : 'invoice', $credit ? 0 : $amount, $credit ? $amount : 0, $label,
            ['date' => $invoice->issued_on, 'source' => $invoice, 'reference' => $invoice->cae ? 'CAE '.$invoice->cae : null]);
    }

    /** Flete de una carga despachada → haber del transportista (le debemos el viaje). */
    public function postFreight(Load $load): ?AccountMovement
    {
        if (! $load->transporter_id || (float) $load->freight_amount <= 0
            || ! in_array($load->status, [LoadStatus::Dispatched, LoadStatus::Delivered], true)) {
            return null;
        }
        $transporter = $load->transporter()->withTrashed()->first();
        $movement = $this->post($transporter, 'freight', 0, (float) $load->freight_amount, 'Flete carga '.$load->number,
            ['date' => $load->dispatched_at ?? today(), 'source' => $load]);
        Load::query()->whereKey($load->id)->whereNull('freight_posted_at')->update(['freight_posted_at' => now()]);

        return $movement;
    }

    /**
     * Liquidación de la compra de fruta: haber del productor (kilos × precio) y, si está configurada,
     * la tasa de asociación por kilo al debe. Un lote se liquida una sola vez (UPDATE condicional).
     *
     * @return array{purchase: AccountMovement, fee: ?AccountMovement}
     */
    public function settleLot(Lot $lot, User $by): array
    {
        return DB::transaction(function () use ($lot, $by) {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            if ($locked->settled_at) {
                throw new BusinessException('El lote '.$locked->code.' ya fue liquidado al productor.');
            }
            if ($locked->status === 'voided') {
                throw new BusinessException('El lote está anulado.');
            }
            $amount = $locked->purchaseAmount();
            if (! $amount || $amount <= 0) {
                throw new BusinessException('Cargá los kilos recibidos y el precio por kilo del lote antes de liquidarlo.');
            }
            $producer = $locked->producer()->withTrashed()->firstOrFail();
            $kg = (float) $locked->kg_received;

            $purchase = $this->post($producer, 'purchase', 0, $amount,
                'Compra de fruta lote '.$locked->code.' · '.num($kg, 2).' kg a '.money((float) $locked->price_per_kg),
                ['date' => $locked->date, 'source' => $locked, 'by' => $by]);

            $fee = null;
            $feePerKg = (float) setting('treasury.association_fee_per_kg', 0);
            if ($feePerKg > 0) {
                $fee = $this->post($producer, 'association_fee', round($kg * $feePerKg, 2), 0,
                    'Tasa de asociación lote '.$locked->code.' · '.num($kg, 2).' kg a '.money($feePerKg),
                    ['date' => $locked->date, 'source' => $locked, 'by' => $by]);
            }

            $locked->forceFill(['settled_at' => now(), 'settled_by' => $by->id])->save();

            return ['purchase' => $purchase, 'fee' => $fee];
        });
    }

    /** Corrige un lote ya liquidado: anula compra y tasa anteriores y liquida con los datos nuevos. */
    public function resettleLot(Lot $lot, User $by, string $reason): array
    {
        return DB::transaction(function () use ($lot, $by, $reason) {
            AccountMovement::query()->where('source_type', 'lot')->where('source_id', $lot->id)
                ->whereIn('type', ['purchase', 'association_fee'])->whereNull('voided_at')->lockForUpdate()->get()
                ->each(fn (AccountMovement $m) => $this->markVoided($m, 'Corrección del lote: '.$reason, $by));
            Lot::query()->whereKey($lot->id)->update(['settled_at' => null, 'settled_by' => null]);

            return $this->settleLot($lot->fresh(), $by);
        });
    }

    /** Imputa lo que falte (facturas autorizadas y fletes de cargas despachadas). Idempotente. */
    public function syncPending(): array
    {
        $invoices = 0;
        Invoice::query()->where('status', InvoiceStatus::Authorized->value)
            ->whereNotExists(fn ($q) => $q->from('account_movements')->whereColumn('account_movements.source_id', 'invoices.id')
                ->where('account_movements.source_type', 'invoice'))
            ->orderBy('id')->each(function (Invoice $invoice) use (&$invoices) {
                if ($this->postInvoice($invoice)) {
                    $invoices++;
                }
            });

        $freights = 0;
        Load::query()->whereNull('freight_posted_at')->where('freight_amount', '>', 0)->whereNotNull('transporter_id')
            ->whereIn('status', [LoadStatus::Dispatched->value, LoadStatus::Delivered->value])
            ->orderBy('id')->each(function (Load $load) use (&$freights) {
                if ($this->postFreight($load)) {
                    $freights++;
                }
            });

        return ['invoices' => $invoices, 'freights' => $freights];
    }

    public function invoiceAffectsAccounts(Invoice $invoice): bool
    {
        return $invoice->arca_mode === 'production' || (bool) setting('treasury.post_test_invoices', false);
    }

    // ------------------------------------------------------------------ Consultas

    public function balance(Model $holder): float
    {
        return round((float) AccountMovement::query()->valid()
            ->where('holder_type', $holder->getMorphClass())->where('holder_id', $holder->getKey())
            ->sum(DB::raw('debit - credit')), 2);
    }

    /**
     * Resumen de cuenta con saldo acumulado. El saldo anterior al período se calcula en la base.
     *
     * @return array{previous: float, rows: Collection, debit: float, credit: float, balance: float}
     */
    public function statement(Model $holder, CarbonInterface $from, CarbonInterface $to, bool $withVoided = false): array
    {
        $base = AccountMovement::query()->where('holder_type', $holder->getMorphClass())->where('holder_id', $holder->getKey());
        $previous = round((float) (clone $base)->valid()->whereDate('date', '<', $from->toDateString())->sum(DB::raw('debit - credit')), 2);

        $rows = (clone $base)->with('user:id,first_name,last_name')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when(! $withVoided, fn ($q) => $q->valid())
            ->orderBy('date')->orderBy('id')->get();

        $running = $previous;
        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as $row) {
            if (! $row->isVoided()) {
                $running = round($running + (float) $row->debit - (float) $row->credit, 2);
                $debit += (float) $row->debit;
                $credit += (float) $row->credit;
            }
            $row->setAttribute('running_balance', $running);
        }

        return ['previous' => $previous, 'rows' => $rows, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'balance' => $running];
    }

    /**
     * Saldos por titular de un tipo (consulta de saldos). `only`: debtors (nos deben) | creditors (les debemos) | nonzero.
     *
     * @return Builder<Model>
     */
    public function balancesQuery(string $type, ?string $search = null, ?string $only = null): Builder
    {
        $class = AccountMovement::holderClass($type);
        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();
        $nameColumn = $type === 'employee' ? 'last_name' : (in_array('business_name', $model->getFillable(), true) ? 'business_name' : 'name');

        $sums = AccountMovement::query()->valid()->where('holder_type', $type)
            ->groupBy('holder_id')
            ->selectRaw('holder_id, SUM(debit) as total_debit, SUM(credit) as total_credit, SUM(debit - credit) as balance, MAX(date) as last_date');

        $query = $class::query()->withTrashed()
            ->leftJoinSub($sums, 'acc', 'acc.holder_id', '=', $table.'.id')
            ->select($table.'.*')
            ->selectRaw('COALESCE(acc.total_debit, 0) as total_debit, COALESCE(acc.total_credit, 0) as total_credit, COALESCE(acc.balance, 0) as balance, acc.last_date')
            ->where(fn ($q) => $q->whereNull($table.'.deleted_at')->orWhereNotNull('acc.holder_id'));

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($table, $nameColumn, $like, $model) {
                $q->where($table.'.'.$nameColumn, 'like', $like);
                foreach (['first_name', 'cuit', 'code', 'dni'] as $column) {
                    if (in_array($column, $model->getFillable(), true)) {
                        $q->orWhere($table.'.'.$column, 'like', $like);
                    }
                }
            });
        }

        match ($only) {
            'debtors' => $query->whereRaw('COALESCE(acc.balance, 0) > 0.004'),
            'creditors' => $query->whereRaw('COALESCE(acc.balance, 0) < -0.004'),
            'nonzero' => $query->whereRaw('ABS(COALESCE(acc.balance, 0)) > 0.004'),
            default => null,
        };

        return $query->orderBy($table.'.'.$nameColumn);
    }

    /** Totales generales por tipo de titular: [type => [debtors, creditors]]. */
    public function totalsByHolderType(): array
    {
        $rows = AccountMovement::query()->valid()
            ->selectRaw('holder_type, holder_id, SUM(debit - credit) as balance')
            ->groupBy('holder_type', 'holder_id')->toBase()->get();

        $totals = [];
        foreach (array_keys(AccountMovement::HOLDERS) as $type) {
            $totals[$type] = ['debtors' => 0.0, 'creditors' => 0.0];
        }
        foreach ($rows as $row) {
            $balance = (float) $row->balance;
            if (! isset($totals[$row->holder_type])) {
                continue;
            }
            if ($balance > 0.004) {
                $totals[$row->holder_type]['debtors'] += $balance;
            } elseif ($balance < -0.004) {
                $totals[$row->holder_type]['creditors'] += -$balance;
            }
        }

        return array_map(fn ($t) => ['debtors' => round($t['debtors'], 2), 'creditors' => round($t['creditors'], 2)], $totals);
    }

    public function sourced(Model $source, string $type, Model $holder): ?AccountMovement
    {
        return AccountMovement::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->where('type', $type)->where('holder_type', $holder->getMorphClass())->where('holder_id', $holder->getKey())->first();
    }

    public static function cashCategory(Model $holder, string $direction): string
    {
        if ($direction === 'collection') {
            return 'collection';
        }

        return match ($holder->getMorphClass()) {
            'producer' => 'producer_payment',
            'transporter' => 'freight',
            'provider' => 'provider_payment',
            'employee' => 'wages',
            default => 'other',
        };
    }

    private function assertHolder(Model $holder): void
    {
        if (! array_key_exists($holder->getMorphClass(), AccountMovement::HOLDERS)) {
            throw new \InvalidArgumentException('Titular de cuenta corriente inválido.');
        }
    }
}
