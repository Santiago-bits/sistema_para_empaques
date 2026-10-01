<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Load;
use App\Services\AuditService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
        $invoice->load(['client', 'items', 'loadRecord', 'remito', 'creator', 'arcaRecords.user', 'stateHistories.user']);

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

        return redirect()->route('invoices.show', $invoice)->with(
            $result->status === InvoiceStatus::Authorized ? 'success' : 'error',
            $result->status === InvoiceStatus::Authorized
                ? 'Comprobante autorizado. CAE '.$result->cae.'.'
                : 'ARCA rechazó el comprobante: '.$result->last_error
        );
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->invoices->void($invoice, $data['reason']);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Comprobante anulado.');
    }

    public function pdf(Invoice $invoice, AuditService $audit): Response
    {
        $invoice->load(['client', 'items']);
        $audit->log('print', $invoice, description: 'Descargó el PDF del comprobante '.$invoice->formattedNumber());

        $qr = null;
        if ($invoice->status === InvoiceStatus::Authorized) {
            // QR fiscal (RG 4892): JSON en base64 apuntando al sitio de ARCA.
            $payload = base64_encode(json_encode([
                'ver' => 1, 'fecha' => $invoice->issued_on->format('Y-m-d'), 'cuit' => (int) preg_replace('/\D/', '', (string) setting('arca.cuit', setting('company.cuit'))),
                'ptoVta' => $invoice->point_of_sale, 'tipoCmp' => $invoice->voucher_type, 'nroCmp' => $invoice->number,
                'importe' => (float) $invoice->total_amount, 'moneda' => $invoice->currency === 'USD' ? 'DOL' : 'PES',
                'ctz' => (float) $invoice->exchange_rate, 'tipoDocRec' => strlen((string) $invoice->client->cuit) === 11 ? 80 : 99,
                'nroDocRec' => (int) preg_replace('/\D/', '', (string) $invoice->client->cuit) ?: 0, 'tipoCodAut' => 'E', 'codAut' => (int) $invoice->cae,
            ]));
            $svg = (new Writer(new ImageRenderer(new RendererStyle(140, 1), new SvgImageBackEnd)))->writeString('https://www.arca.gob.ar/fe/qr/?p='.$payload);
            $qr = 'data:image/svg+xml;base64,'.base64_encode($svg);
        }

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice, 'qr' => $qr])->setPaper('a4')
            ->download('comprobante-'.$invoice->formattedNumber().'.pdf');
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
        ];
    }
}
