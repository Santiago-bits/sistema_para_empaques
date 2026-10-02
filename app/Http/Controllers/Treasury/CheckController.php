<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\AccountMovement;
use App\Models\Check;
use App\Services\AccountService;
use App\Services\CheckService;
use App\Services\ExportService;
use App\Services\Reports\ReportDataset;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Cheques: cartera, por vencer, por cobrar, emitidos / por pagar, endosados y rechazados. */
class CheckController extends Controller
{
    /** Vistas rápidas como en el sistema anterior. */
    public const VIEWS = [
        'portfolio' => 'En cartera',
        'due' => 'Por vencer',
        'receivable' => 'Por cobrar',
        'payable' => 'Emitidos / por pagar',
        'endorsed' => 'Endosados',
        'rejected' => 'Rechazados',
        'all' => 'Todos',
    ];

    public function __construct(private readonly CheckService $checks)
    {
    }

    public function index(Request $request): View|Response
    {
        $data = $request->validate([
            'view' => ['nullable', Rule::in(array_keys(self::VIEWS))],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
        ]);
        $view = $data['view'] ?? 'portfolio';
        $query = $this->filtered($view, $data);

        if (isset($data['format'])) {
            return $this->export($request, $view, $query, $data['format']);
        }

        $summary = Check::query()->whereIn('status', ['in_portfolio', 'deposited', 'issued'])
            ->selectRaw('kind, status, COUNT(*) as n, SUM(amount) as total')->groupBy('kind', 'status')->toBase()->get();

        return view('treasury.checks.index', [
            'view' => $view,
            'checks' => (clone $query)->with('receivedFrom', 'deliveredTo')->paginate($this->perPage($request))->withQueryString(),
            'viewTotal' => round((float) (clone $query)->reorder()->sum('amount'), 2),
            'portfolio' => (float) $summary->where('status', 'in_portfolio')->sum('total'),
            'deposited' => (float) $summary->where('status', 'deposited')->sum('total'),
            'issued' => (float) $summary->where('status', 'issued')->sum('total'),
            'dueSoon' => Check::query()->pending()->whereDate('payment_date', '<=', today()->addDays((int) setting('treasury.check_warning_days', 7))->toDateString())->count(),
        ]);
    }

    public function create(): View
    {
        return view('treasury.checks.form', ['holders' => $this->holderOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Check::KINDS))],
            'holder' => ['nullable', 'string', 'regex:/^(client|producer|transporter|provider|employee):\d+$/'],
            'bank' => ['required', 'string', 'max:80'],
            'number' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'issued_on' => ['required', 'date'],
            'payment_date' => ['required', 'date', 'after_or_equal:issued_on'],
            'electronic' => ['boolean'],
            'issuer_name' => ['nullable', 'string', 'max:120'],
            'issuer_cuit' => ['nullable', 'string', 'max:13'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['kind' => 'tipo', 'holder' => 'titular', 'bank' => 'banco', 'number' => 'número', 'amount' => 'importe',
            'issued_on' => 'fecha de emisión', 'payment_date' => 'fecha de cobro', 'issuer_name' => 'librador', 'issuer_cuit' => 'CUIT del librador']);

        $user = $request->user();
        if (! empty($data['holder'])) {
            // Con titular: es un cobro (de terceros) o un pago (propio) en su cuenta corriente.
            abort_unless($user->can('accounts.manage'), 403);
            [$type, $id] = explode(':', $data['holder']);
            $class = AccountMovement::holderClass($type);
            $holder = $class::query()->withTrashed()->findOrFail((int) $id);
            $movement = app(AccountService::class)->registerPayment($holder, [
                'direction' => $data['kind'] === 'third_party' ? 'collection' : 'payment',
                'amount' => $data['amount'],
                'method' => 'check',
                'date' => today()->toDateString(),
                'check' => $data,
            ], $user);
            $check = $movement->source;
        } else {
            $check = $this->checks->create($data, $user);
        }

        return redirect()->route('checks.show', $check)->with('success', 'Cheque registrado.');
    }

    public function show(Check $check): View
    {
        $check->load('receivedFrom', 'deliveredTo', 'creator:id,first_name,last_name');

        return view('treasury.checks.show', [
            'check' => $check,
            'movements' => AccountMovement::query()->where('source_type', 'check')->where('source_id', $check->id)->with('holder')->orderBy('id')->get(),
        ]);
    }

    public function edit(Check $check): View
    {
        return view('treasury.checks.edit', ['check' => $check]);
    }

    public function update(Request $request, Check $check): RedirectResponse
    {
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'bank' => ['required', 'string', 'max:80'],
            'number' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'issued_on' => ['required', 'date'],
            'payment_date' => ['required', 'date', 'after_or_equal:issued_on'],
            'electronic' => ['boolean'],
            'issuer_name' => ['nullable', 'string', 'max:120'],
            'issuer_cuit' => ['nullable', 'string', 'max:13'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['bank' => 'banco', 'number' => 'número', 'amount' => 'importe', 'issued_on' => 'fecha de emisión',
            'payment_date' => 'fecha de cobro', 'reason' => 'motivo de la corrección']);

        $this->checks->update($check, $data, $data['reason'], $request->user());

        return redirect()->route('checks.show', $check)->with('success', 'Cheque corregido.');
    }

    public function transition(Request $request, Check $check): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['deposited', 'cashed', 'paid', 'rejected', 'voided'])],
            'notes' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], ['status' => 'estado', 'notes' => 'motivo', 'date' => 'fecha']);

        $this->checks->transition($check, $data['status'], $request->user(), $data['notes'] ?? null, $data['date'] ?? null);

        return redirect()->route('checks.show', $check)->with('success', 'Cheque: '.Check::STATUSES[$data['status']][0].'.');
    }

    private function filtered(string $view, array $data): Builder
    {
        $warning = today()->addDays((int) setting('treasury.check_warning_days', 7))->toDateString();

        $query = match ($view) {
            'portfolio' => Check::query()->where('kind', 'third_party')->where('status', 'in_portfolio'),
            'due' => Check::query()->pending()->whereDate('payment_date', '<=', $warning),
            'receivable' => Check::query()->where('kind', 'third_party')->whereIn('status', ['in_portfolio', 'deposited']),
            'payable' => Check::query()->where('kind', 'own')->where('status', 'issued'),
            'endorsed' => Check::query()->where('status', 'endorsed'),
            'rejected' => Check::query()->where('status', 'rejected'),
            default => Check::query(),
        };

        if (! empty($data['q'])) {
            $like = '%'.addcslashes($data['q'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('bank', 'like', $like)
                ->orWhere('issuer_name', 'like', $like)->orWhere('issuer_cuit', 'like', $like));
        }
        if (! empty($data['from'])) {
            $query->whereDate('payment_date', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('payment_date', '<=', $data['to']);
        }

        return $query->orderBy('payment_date')->orderBy('id');
    }

    private function export(Request $request, string $view, Builder $query, string $format): Response
    {
        $dataset = new ReportDataset('cheques_'.$view, 'Cheques · '.self::VIEWS[$view], [
            'kind' => ['label' => 'Tipo', 'type' => 'text'],
            'bank' => ['label' => 'Banco', 'type' => 'text'],
            'number' => ['label' => 'Número', 'type' => 'text'],
            'issuer' => ['label' => 'Librador', 'type' => 'text'],
            'issued_on' => ['label' => 'Emisión', 'type' => 'date'],
            'payment_date' => ['label' => 'Fecha de cobro', 'type' => 'date'],
            'days' => ['label' => 'Días al cobro', 'type' => 'int'],
            'amount' => ['label' => 'Importe', 'type' => 'money'],
            'status' => ['label' => 'Estado', 'type' => 'text'],
            'from' => ['label' => 'Recibido de', 'type' => 'text'],
            'to' => ['label' => 'Entregado a', 'type' => 'text'],
        ], fn () => (clone $query)->with('receivedFrom', 'deliveredTo')->cursor()->map(fn (Check $c) => [
            'kind' => Check::KINDS[$c->kind] ?? $c->kind,
            'bank' => $c->bank,
            'number' => $c->number,
            'issuer' => $c->issuer_name,
            'issued_on' => $c->issued_on,
            'payment_date' => $c->payment_date,
            'days' => $c->daysToPayment(),
            'amount' => (float) $c->amount,
            'status' => $c->statusLabel(),
            'from' => $c->receivedFrom ? AccountMovement::holderLabel($c->receivedFrom) : null,
            'to' => $c->deliveredTo ? AccountMovement::holderLabel($c->deliveredTo) : null,
        ]));

        return app(ExportService::class)->download($dataset, $format, ReportFilters::between(today(), today()), $request->user());
    }

    /** @return array<string, array<string, string>> grupo => [tipo:id => nombre] */
    private function holderOptions(): array
    {
        $options = [];
        foreach (AccountMovement::HOLDERS as $type => [, $plural]) {
            $class = AccountMovement::holderClass($type);
            $options[$plural] = $class::query()->where('active', true)->get()
                ->mapWithKeys(fn ($h) => [$type.':'.$h->getKey() => AccountMovement::holderLabel($h)])
                ->sort()->all();
        }

        return $options;
    }
}
