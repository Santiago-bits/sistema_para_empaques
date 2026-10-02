<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\PaymentRequest;
use App\Models\AccountMovement;
use App\Models\Check;
use App\Models\Lot;
use App\Services\AccountService;
use App\Services\ExportService;
use App\Services\Reports\ReportDataset;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Cuentas corrientes: consulta de saldos, resumen de cuenta, cobros, pagos, ajustes y anulaciones. */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function index(Request $request): View|Response
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(AccountMovement::HOLDERS))],
            'q' => ['nullable', 'string', 'max:100'],
            'only' => ['nullable', Rule::in(['debtors', 'creditors', 'nonzero'])],
            'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
        ]);
        $type = $data['type'] ?? 'client';
        $query = $this->accounts->balancesQuery($type, $data['q'] ?? null, $data['only'] ?? null);

        if (isset($data['format'])) {
            return $this->exportBalances($request, $type, $query, $data['format']);
        }

        return view('treasury.accounts.index', [
            'type' => $type,
            'holders' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $this->accounts->totalsByHolderType(),
        ]);
    }

    public function show(Request $request, string $type, int $holder): View|Response
    {
        $model = $this->holder($type, $holder);
        [$from, $to] = $this->period($request);
        $statement = $this->accounts->statement($model, $from, $to, $request->boolean('anulados'));

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            return $this->exportStatement($request, $type, $model, $statement, $from, $to, $request->query('format'));
        }

        return view('treasury.accounts.show', [
            'type' => $type,
            'holder' => $model,
            'label' => AccountMovement::holderLabel($model),
            'statement' => $statement,
            'balance' => $this->accounts->balance($model),
            'from' => $from,
            'to' => $to,
            'portfolio' => Check::query()->where('kind', 'third_party')->where('status', 'in_portfolio')->orderBy('payment_date')->get(),
            'cashOpen' => app(\App\Services\CashService::class)->current() !== null,
        ]);
    }

    public function print(Request $request, string $type, int $holder): View
    {
        $model = $this->holder($type, $holder);
        [$from, $to] = $this->period($request);

        return view('treasury.accounts.print', [
            'type' => $type,
            'holder' => $model,
            'label' => AccountMovement::holderLabel($model),
            'statement' => $this->accounts->statement($model, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function payment(PaymentRequest $request, string $type, int $holder): RedirectResponse
    {
        $model = $this->holder($type, $holder);
        $movement = $this->accounts->registerPayment($model, $request->validated(), $request->user());
        $what = $movement->credit > 0 ? 'Cobro' : ($movement->type === 'advance' ? 'Adelanto' : 'Pago');

        return redirect()->route('accounts.show', [$type, $holder])
            ->with('success', $what.' de '.money($movement->credit > 0 ? $movement->credit : $movement->debit).' registrado.');
    }

    public function adjust(Request $request, string $type, int $holder): RedirectResponse
    {
        $model = $this->holder($type, $holder);
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['debit', 'credit', 'opening_debit', 'opening_credit', 'advance'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ], [], ['kind' => 'tipo', 'amount' => 'importe', 'description' => 'motivo / descripción', 'date' => 'fecha']);

        $this->accounts->adjust($model, $data['kind'], (float) $data['amount'], $data['description'], $data['date'], $request->user());

        return redirect()->route('accounts.show', [$type, $holder])->with('success', 'Movimiento registrado.');
    }

    public function void(Request $request, AccountMovement $movement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->accounts->void($movement, $data['reason'], $request->user());

        return back()->with('success', 'Movimiento anulado. El saldo se recalculó.');
    }

    public function correct(Request $request, AccountMovement $movement): RedirectResponse
    {
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:80'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['amount' => 'importe', 'date' => 'fecha', 'description' => 'detalle', 'reference' => 'referencia', 'reason' => 'motivo de la corrección']);

        $this->accounts->correct($movement, $data, $data['reason'], $request->user());

        return back()->with('success', 'Movimiento corregido. El saldo se recalculó.');
    }

    public function sync(): RedirectResponse
    {
        $result = $this->accounts->syncPending();

        return back()->with('success', $result['invoices'] + $result['freights'] === 0
            ? 'No había comprobantes ni fletes pendientes de imputar.'
            : 'Imputados: '.$result['invoices'].' comprobante(s) y '.$result['freights'].' flete(s).');
    }

    public function settleLot(Request $request, Lot $lot): RedirectResponse
    {
        $result = $this->accounts->settleLot($lot, $request->user());
        $net = (float) $result['purchase']->credit - (float) ($result['fee']?->debit ?? 0);

        return redirect()->route('lots.show', $lot)->with('success', 'Lote liquidado: '.money($net).' a favor del productor.');
    }

    private function holder(string $type, int $id): Model
    {
        $class = AccountMovement::holderClass($type);

        return $class::query()->withTrashed()->findOrFail($id);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : today()->subMonths(3)->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : today();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function exportBalances(Request $request, string $type, $query, string $format): Response
    {
        $dataset = new ReportDataset('saldos_'.$type, 'Saldos de cuentas corrientes · '.AccountMovement::HOLDERS[$type][1], [
            'name' => ['label' => AccountMovement::HOLDERS[$type][0], 'type' => 'text'],
            'cuit' => ['label' => 'CUIT / DNI', 'type' => 'text'],
            'debit' => ['label' => 'Debe', 'type' => 'money'],
            'credit' => ['label' => 'Haber', 'type' => 'money'],
            'balance' => ['label' => 'Saldo (+ nos debe / − le debemos)', 'type' => 'money'],
            'last' => ['label' => 'Último movimiento', 'type' => 'date'],
        ], fn () => (clone $query)->cursor()->map(fn (Model $h) => [
            'name' => AccountMovement::holderLabel($h),
            'cuit' => $h->getAttribute('cuit') ?: $h->getAttribute('dni'),
            'debit' => round((float) $h->getAttribute('total_debit'), 2),
            'credit' => round((float) $h->getAttribute('total_credit'), 2),
            'balance' => round((float) $h->getAttribute('balance'), 2),
            'last' => $h->getAttribute('last_date'),
        ]));

        return app(ExportService::class)->download($dataset, $format, ReportFilters::between(today(), today()), $request->user());
    }

    private function exportStatement(Request $request, string $type, Model $holder, array $statement, Carbon $from, Carbon $to, string $format): Response
    {
        $rows = [['date' => $from->toDateString(), 'type' => '', 'description' => 'Saldo anterior', 'debit' => null, 'credit' => null, 'balance' => $statement['previous']]];
        foreach ($statement['rows'] as $row) {
            $rows[] = [
                'date' => $row->date, 'type' => AccountMovement::TYPES[$row->type] ?? $row->type,
                'description' => $row->description.($row->isVoided() ? ' (ANULADO)' : ''),
                'debit' => (float) $row->debit ?: null, 'credit' => (float) $row->credit ?: null, 'balance' => $row->running_balance,
            ];
        }
        $dataset = new ReportDataset('cuenta_'.$type.'_'.$holder->getKey(), 'Cuenta corriente · '.AccountMovement::holderLabel($holder), [
            'date' => ['label' => 'Fecha', 'type' => 'date'],
            'type' => ['label' => 'Tipo', 'type' => 'text'],
            'description' => ['label' => 'Detalle', 'type' => 'text'],
            'debit' => ['label' => 'Debe', 'type' => 'money'],
            'credit' => ['label' => 'Haber', 'type' => 'money'],
            'balance' => ['label' => 'Saldo', 'type' => 'money'],
        ], $rows);

        return app(ExportService::class)->download($dataset, $format, ReportFilters::between($from, $to), $request->user());
    }
}
