<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Load;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['client:id,business_name', 'loadRecord:id,number'])
            ->when($request->filled('status') && InvoiceStatus::tryFrom((string) $request->query('status')),
                fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('voucher_type'), fn ($q) => $q->where('voucher_type', $request->integer('voucher_type')))
            ->when($request->filled('number'), fn ($q) => $q->where('number', (int) preg_replace('/^.*-/', '', (string) $request->query('number'))))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issued_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issued_on', '<=', $request->date('to')))
            ->latest('issued_on')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::options(),
            'types' => Invoice::VOUCHER_TYPES,
            'clients' => Client::query()->orderBy('business_name')->pluck('business_name', 'id'),
            'mode' => setting('arca.mode', 'simulation'),
        ]);
    }

    public function create(Request $request): View
    {
        $load = $request->filled('load_id') ? Load::query()->with('client')->find($request->integer('load_id')) : null;
        $client = $load?->client;
        $invoice = new Invoice([
            'client_id' => $client?->id,
            'load_id' => $load?->id,
            'voucher_type' => $client ? $this->invoices->voucherTypeFor($client) : 6,
            'issued_on' => today(),
            'currency' => 'ARS',
            'exchange_rate' => 1,
        ]);

        return view('invoices.form', $this->formData($invoice, $load ? $this->invoices->suggestedItems($load) : []));
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($request->validated(), $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Comprobante creado en borrador. Revisalo y envialo a ARCA.');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['client', 'items', 'loadRecord', 'remito', 'creator', 'associated', 'arcaRecords.user', 'stateHistories.user']);

        return view('invoices.show', ['invoice' => $invoice, 'mode' => setting('arca.mode', 'simulation')]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Rejected], true)) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Sólo se editan comprobantes en borrador o rechazados.');
        }

        return view('invoices.form', $this->formData($invoice, $invoice->items->map(fn ($i) => $i->only(['description', 'quantity', 'unit', 'unit_price', 'vat_rate']))->all()));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $request->validated());

        return redirect()->route('invoices.show', $invoice)->with('success', 'Comprobante actualizado.');
    }

    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->submit($invoice, $request->user());

        return redirect()->route('invoices.show', $invoice)->with(...$this->resultFlash($result));
    }

    /** Verifica en ARCA un comprobante que quedó pendiente (sin respuesta). */
    public function reconcile(Request $request, Invoice $invoice): RedirectResponse
    {
        $result = $this->invoices->reconcile($invoice, $request->user());

        return redirect()->route('invoices.show', $invoice)->with(...$this->resultFlash($result));
    }

    /** @return array{0: string, 1: string} */
    private function resultFlash(Invoice $result): array
    {
        return match ($result->status) {
            InvoiceStatus::Authorized => ['success', 'Comprobante autorizado. CAE '.$result->cae.'.'],
            InvoiceStatus::Pending => ['error', 'ARCA no respondió. El comprobante quedó pendiente: usá «Verificar en ARCA» antes de reenviarlo.'],
            default => ['error', 'ARCA rechazó el comprobante: '.$result->last_error],
        };
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->invoices->void($invoice, $data['reason']);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Comprobante anulado.');
    }

    public function pdf(Invoice $invoice, InvoicePdfService $pdf): Response
    {
        return $pdf->download($invoice);
    }

    private function formData(Invoice $invoice, array $items): array
    {
        return [
            'invoice' => $invoice,
            'items' => $items ?: [['description' => '', 'quantity' => '', 'unit' => 'kg', 'unit_price' => '', 'vat_rate' => '21']],
            'clients' => Client::query()->where('active', true)->orderBy('business_name')->get(['id', 'business_name', 'tax_condition']),
            'types' => Invoice::VOUCHER_TYPES,
            'vatRates' => InvoiceService::VAT_RATES,
            'loads' => Load::query()->whereIn('status', ['closed', 'dispatched', 'delivered'])->latest('date')->limit(200)->pluck('number', 'id'),
            // Facturas autorizadas que una nota de crédito puede ajustar.
            'associable' => Invoice::query()->with('client:id,business_name')->where('status', InvoiceStatus::Authorized->value)
                ->whereIn('voucher_type', array_values(Invoice::CREDIT_NOTE_FOR))->latest('issued_on')->limit(300)->get()
                ->mapWithKeys(fn (Invoice $i) => [$i->id => $i->voucherLabel().' '.$i->formattedNumber().' · '.$i->client?->business_name.' · '.money($i->total_amount)])->all(),
        ];
    }
}
