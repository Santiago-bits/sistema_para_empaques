<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LoadStatus;
use App\Events\InvoiceAuthorized;
use App\Events\InvoiceCreated;
use App\Exceptions\BusinessException;
use App\Models\ArcaRecord;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Load;
use App\Models\Remito;
use App\Models\User;
use App\Services\Arca\ArcaGatewayFactory;
use App\Support\ErrorReporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprobantes y envío a ARCA. Los importes SIEMPRE se calculan acá (nunca se toman
 * del navegador). El envío pasa la factura a "Pendiente" dentro de una transacción con
 * bloqueo de fila, así dos usuarios no pueden enviarla dos veces.
 */
class InvoiceService
{
    public const VAT_RATES = ['21' => '21 %', '10.5' => '10,5 %', '0' => 'Exento (0 %)'];

    public function __construct(
        private readonly StateTransitionService $states,
        private readonly AuditService $audit,
        private readonly ArcaGatewayFactory $gateways,
        private readonly LoadService $loads,
    ) {
    }

    /** Tipo de comprobante según emisor y condición fiscal del cliente. */
    public function voucherTypeFor(Client $client): int
    {
        if (setting('arca.emitter_condition', 'RI') === 'MT') {
            return 11; // Factura C
        }

        return $client->tax_condition === 'RI' ? 1 : 6; // A o B
    }

    /** Ítems sugeridos desde una carga: uno por variedad y tamaño, en kg. */
    public function suggestedItems(Load $load): array
    {
        return array_map(fn ($row) => [
            'description' => trim($row['variety'].' '.$row['size']).' ('.$row['crates'].' cajones)',
            'quantity' => $row['kg'],
            'unit' => 'kg',
            'unit_price' => 0,
            'vat_rate' => setting('arca.emitter_condition', 'RI') === 'MT' ? 0 : 21,
        ], $this->loads->byVarietySize($load));
    }

    public function create(array $data, User $by): Invoice
    {
        $invoice = DB::transaction(function () use ($data, $by) {
            $client = Client::query()->findOrFail($data['client_id']);
            $load = ! empty($data['load_id']) ? Load::query()->find($data['load_id']) : null;
            $remito = $load ? Remito::query()->where('active_load_id', $load->id)->first() : null;

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'load_id' => $load?->id,
                'remito_id' => $remito?->id,
                'voucher_type' => $data['voucher_type'] ?? $this->voucherTypeFor($client),
                'point_of_sale' => (int) setting('arca.point_of_sale', 1),
                'issued_on' => $data['issued_on'] ?? today(),
                'currency' => $data['currency'] ?? 'ARS',
                'exchange_rate' => ($data['currency'] ?? 'ARS') === 'USD' ? ($data['exchange_rate'] ?? 1) : 1,
                'status' => InvoiceStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $this->syncItems($invoice, $data['items'] ?? []);
            $this->states->recordInitial($invoice, InvoiceStatus::Draft, 'Comprobante creado');

            return $invoice;
        });

        InvoiceCreated::dispatch($invoice);

        return $invoice;
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [InvoiceStatus::Draft, InvoiceStatus::Rejected], true)) {
                throw new BusinessException('Sólo se pueden modificar comprobantes en borrador o rechazados.');
            }
            $locked->update([
                'client_id' => $data['client_id'],
                'voucher_type' => $data['voucher_type'],
                'issued_on' => $data['issued_on'],
                'currency' => $data['currency'],
                'exchange_rate' => $data['currency'] === 'USD' ? $data['exchange_rate'] : 1,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->syncItems($locked, $data['items'] ?? []);

            return $locked->refresh();
        });
    }

    /**
     * Envía a ARCA. Paso 1 (transacción + lock): Borrador/Rechazado → Pendiente.
     * Paso 2: llamada al gateway. Paso 3 (transacción): Autorizado (CAE) o Rechazado.
     */
    public function submit(Invoice $invoice, User $by): Invoice
    {
        $gateway = $this->gateways->make();

        $pending = DB::transaction(function () use ($invoice, $gateway) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [InvoiceStatus::Draft, InvoiceStatus::Rejected], true)) {
                throw new BusinessException('El comprobante ya fue enviado ('.$locked->status->label().').');
            }
            if ((float) $locked->total_amount <= 0 || ! $locked->items()->exists()) {
                throw new BusinessException('El comprobante no tiene importes: cargá los precios antes de enviarlo.');
            }
            $client = $locked->client;
            if (in_array($locked->voucher_type, [1, 3], true) && strlen(preg_replace('/\D/', '', (string) $client->cuit)) !== 11) {
                throw new BusinessException('Para Factura A el cliente debe tener CUIT.');
            }

            $this->states->transition($locked, InvoiceStatus::Pending, 'Enviado a ARCA ('.$gateway->mode().')', [
                'arca_mode' => $gateway->mode(),
                'attempts' => $locked->attempts + 1,
            ]);

            return $locked->refresh();
        });

        try {
            $result = $gateway->authorize($pending);
        } catch (Throwable $e) {
            $code = ErrorReporter::capture($e, 'arca');
            $result = \App\Services\Arca\ArcaResult::failure('Error al comunicarse con ARCA (código '.$code.').');
        }

        return DB::transaction(function () use ($pending, $result, $by, $gateway) {
            ArcaRecord::query()->create([
                'invoice_id' => $pending->id,
                'mode' => $gateway->mode(),
                'operation' => $result->operation,
                'status' => $result->approved ? 'success' : 'error',
                'request' => $this->sanitize($result->request),
                'response' => $this->sanitize($result->response),
                'error_message' => $result->error,
                'attempt' => $pending->attempts,
                'user_id' => $by->id,
                'created_at' => now(),
            ]);

            if (! $result->approved) {
                $this->states->transition($pending, InvoiceStatus::Rejected, 'Rechazado por ARCA', ['last_error' => $result->error]);
                ErrorReporter::capture(new \RuntimeException('ARCA rechazó el comprobante #'.$pending->id.': '.$result->error), 'arca');

                return $pending->refresh();
            }

            try {
                $this->states->transition($pending, InvoiceStatus::Authorized, 'CAE '.$result->cae, [
                    'number' => $result->number,
                    'cae' => $result->cae,
                    'cae_expires_on' => $result->caeExpiresOn,
                    'last_error' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessException('Número de comprobante duplicado. Reintentá el envío.');
            }

            $authorized = $pending->refresh();
            $this->audit->log('invoice', $authorized, null, ['number' => $authorized->formattedNumber(), 'cae' => $authorized->cae],
                'Comprobante autorizado '.$authorized->voucherLabel().' '.$authorized->formattedNumber());

            if ($authorized->load_id && ($load = Load::query()->find($authorized->load_id))
                && in_array($load->status, [LoadStatus::Dispatched, LoadStatus::Delivered], true)) {
                $this->loads->markInvoiced($load, $by);
            }

            InvoiceAuthorized::dispatch($authorized);

            return $authorized;
        });
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === InvoiceStatus::Authorized) {
                throw new BusinessException('Un comprobante autorizado no se anula: corresponde emitir una nota de crédito.');
            }

            return $this->states->transition($locked, InvoiceStatus::Voided, 'Anulado: '.$reason);
        });
    }

    /** Recalcula ítems y totales en el servidor. */
    private function syncItems(Invoice $invoice, array $items): void
    {
        $isC = in_array((int) $invoice->voucher_type, [11, 13], true);
        $invoice->items()->delete();
        $net = 0.0;
        $vat = 0.0;

        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 2);
            $price = round((float) $item['unit_price'], 4);
            $rate = $isC ? 0.0 : (float) $item['vat_rate'];
            $subtotal = round($quantity * $price, 2);
            $net += $subtotal;
            $vat += round($subtotal * $rate / 100, 2);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => mb_substr((string) $item['description'], 0, 255),
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'kg',
                'unit_price' => $price,
                'vat_rate' => $rate,
                'subtotal' => $subtotal,
            ]);
        }

        $invoice->forceFill([
            'net_amount' => round($net, 2),
            'vat_amount' => round($vat, 2),
            'total_amount' => round($net + $vat, 2),
        ])->save();
    }

    /** Nunca guardar token/sign/certificados en el historial. */
    private function sanitize(array $data): array
    {
        return app(AuditService::class)->sanitize(array_diff_key($data, array_flip(['Token', 'Sign', 'token', 'sign'])));
    }
}
