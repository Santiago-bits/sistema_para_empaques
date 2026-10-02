<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

/** PDF del comprobante con el QR fiscal de ARCA (RG 4892) cuando está autorizado. */
class InvoicePdfService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function download(Invoice $invoice): Response
    {
        $invoice->load(['client', 'items', 'associated']);
        $this->audit->log('print', $invoice, description: 'Descargó el PDF del comprobante '.$invoice->formattedNumber());

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice, 'qr' => $this->qr($invoice)])->setPaper('a4')
            ->download('comprobante-'.$invoice->formattedNumber().'.pdf');
    }

    private function qr(Invoice $invoice): ?string
    {
        if ($invoice->status !== InvoiceStatus::Authorized) {
            return null;
        }

        // JSON en base64 apuntando al sitio de ARCA.
        $payload = base64_encode(json_encode([
            'ver' => 1, 'fecha' => $invoice->issued_on->format('Y-m-d'), 'cuit' => (int) preg_replace('/\D/', '', (string) setting('arca.cuit', setting('company.cuit'))),
            'ptoVta' => $invoice->point_of_sale, 'tipoCmp' => $invoice->voucher_type, 'nroCmp' => $invoice->number,
            'importe' => (float) $invoice->total_amount, 'moneda' => $invoice->currency === 'USD' ? 'DOL' : 'PES',
            'ctz' => (float) $invoice->exchange_rate, 'tipoDocRec' => strlen((string) $invoice->client->cuit) === 11 ? 80 : 99,
            'nroDocRec' => (int) preg_replace('/\D/', '', (string) $invoice->client->cuit) ?: 0, 'tipoCodAut' => 'E', 'codAut' => (int) $invoice->cae,
        ]));
        $svg = (new Writer(new ImageRenderer(new RendererStyle(140, 1), new SvgImageBackEnd)))->writeString('https://www.arca.gob.ar/fe/qr/?p='.$payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
