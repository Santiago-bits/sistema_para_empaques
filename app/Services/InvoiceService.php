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
use App\Services\Arca\ArcaResult;
use App\Services\Arca\VatCalculator;
use App\Support\ErrorReporter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprobantes y envío a ARCA. Los importes SIEMPRE se calculan acá (nunca se toman
 * del navegador), en centavos y con el IVA por alícuota sobre la base agrupada.
 *
 * Garantías:
 *  - Dos usuarios no pueden enviar el mismo comprobante (lock de fila) ni pedir el mismo
 *    número a ARCA (lock de numeración por modo + punto de venta + tipo).
 *  - Un CAE obtenido nunca se pierde: se guarda primero y los efectos secundarios van después.
 *  - Si ARCA no responde, el comprobante queda Pendiente hasta verificarlo (reconcile()),
 *    porque reenviarlo a ciegas podría duplicarlo en ARCA.
 *  - Una carga tiene a lo sumo una factura viva; las notas de crédito indican la factura que ajustan.
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
            // Lock de la carga: dos operarios no pueden facturar la misma carga a la vez.
            $load = ! empty($data['load_id']) ? Load::query()->whereKey($data['load_id'])->lockForUpdate()->first() : null;
            $remito = $load ? Remito::query()->where('active_load_id', $load->id)->first() : null;
            $voucherType = (int) ($data['voucher_type'] ?? $this->voucherTypeFor($client));
            $associated = $this->validateAssociation($voucherType, $client, $data['associated_invoice_id'] ?? null);
            if ($load) {
                $this->ensureLoadNotInvoiced($load, $voucherType);
            }

            $invoice = Invoice::query()->create([
                'client_id' => $client->id,
                'load_id' => $load?->id,
                'associated_invoice_id' => $associated?->id,
                'remito_id' => $remito?->id,
                'voucher_type' => $voucherType,
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
            $client = Client::query()->findOrFail($data['client_id']);
            $associated = $this->validateAssociation((int) $data['voucher_type'], $client, $data['associated_invoice_id'] ?? null, $locked->id);
            if ($locked->load_id) {
                $this->ensureLoadNotInvoiced(Load::query()->whereKey($locked->load_id)->lockForUpdate()->firstOrFail(), (int) $data['voucher_type'], $locked->id);
            }

            $locked->update([
                'client_id' => $data['client_id'],
                'associated_invoice_id' => $associated?->id,
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
     * Envía a ARCA.
     *  1. (transacción + lock) Borrador/Rechazado → Pendiente.
     *  2. (lock de numeración) gateway → resultado.
     *  3. Se guarda el resultado en una transacción mínima; después, los efectos secundarios.
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
            if ($locked->isCreditNote()) {
                $this->validateAssociation((int) $locked->voucher_type, $client, $locked->associated_invoice_id, $locked->id);
            }
            if ($locked->load_id) {
                $this->ensureLoadNotInvoiced(Load::query()->whereKey($locked->load_id)->lockForUpdate()->firstOrFail(), (int) $locked->voucher_type, $locked->id);
            }

            $this->states->transition($locked, InvoiceStatus::Pending, 'Enviado a ARCA ('.$gateway->mode().')', [
                'arca_mode' => $gateway->mode(),
                'attempts' => $locked->attempts + 1,
            ]);

            return $locked->refresh();
        });

        $lock = $this->numberingLock($gateway->mode(), $pending);
        try {
            $lock->block(60);
        } catch (LockTimeoutException) {
            $result = ArcaResult::failure('Otro envío al mismo punto de venta está en curso. Reintentá en un momento.');

            return $this->storeResult($pending, $result, $gateway->mode(), $by);
        }

        try {
            try {
                $result = $gateway->authorize($pending);
            } catch (Throwable $e) {
                // Falló antes de enviar el comprobante (autenticación, último número, etc.): no hubo autorización.
                $code = ErrorReporter::capture($e, 'arca');
                $result = ArcaResult::failure($e instanceof BusinessException ? $e->getMessage() : 'Error al comunicarse con ARCA (código '.$code.').');
            }

            $saved = $this->storeResult($pending, $result, $gateway->mode(), $by);
        } finally {
            $lock->release();
        }

        if ($saved->status === InvoiceStatus::Authorized) {
            $this->afterAuthorized($saved, $by);
        }

        return $saved;
    }

    /**
     * Verifica en ARCA un comprobante que quedó Pendiente. Si ARCA lo tiene (mismo total y
     * documento) se completa con su CAE; si no, pasa a Rechazado y se puede reenviar.
     */
    public function reconcile(Invoice $invoice, User $by): Invoice
    {
        $invoice->refresh()->loadMissing('client');
        if ($invoice->status !== InvoiceStatus::Pending) {
            throw new BusinessException('Sólo se verifican comprobantes pendientes de respuesta de ARCA.');
        }

        $gateway = $this->gateways->make($invoice->arca_mode ?: null);
        $lastRequest = $invoice->arcaRecords()->where('operation', 'FECAESolicitar')->latest('id')->value('request');
        $attempted = (int) ((is_array($lastRequest) ? $lastRequest : (array) json_decode((string) $lastRequest, true))['CbteDesde'] ?? 0);
        $request = ['PtoVta' => $invoice->point_of_sale, 'CbteTipo' => $invoice->voucher_type, 'CbteNro' => $attempted];

        $lock = $this->numberingLock($gateway->mode(), $invoice);
        $lock->block(60);
        try {
            $found = $attempted > 0 ? $gateway->consult((int) $invoice->point_of_sale, (int) $invoice->voucher_type, $attempted) : null;
            $docNumber = preg_replace('/\D/', '', (string) ($invoice->client->cuit ?: $invoice->client->dni)) ?: '0';
            $matches = $found !== null
                && abs($found['total'] - (float) $invoice->total_amount) < 0.01
                && ltrim($found['doc_number'], '0') === ltrim($docNumber, '0');

            $result = $matches
                ? new ArcaResult(true, $attempted, $found['cae'], $found['cae_expires_on'], request: $request, response: $found, operation: 'FECompConsultar')
                : ArcaResult::failure($found === null
                    ? 'ARCA no registró el comprobante: se puede reenviar.'
                    : 'El número '.$attempted.' en ARCA corresponde a otro comprobante (total o documento distintos): se puede reenviar.',
                    $request, $found ?? [], 'FECompConsultar');

            $saved = $this->storeResult($invoice, $result, $gateway->mode(), $by);
        } finally {
            $lock->release();
        }

        if ($saved->status === InvoiceStatus::Authorized) {
            $this->afterAuthorized($saved, $by);
        }

        return $saved;
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === InvoiceStatus::Authorized) {
                throw new BusinessException('Un comprobante autorizado no se anula: corresponde emitir una nota de crédito.');
            }
            if ($locked->status === InvoiceStatus::Pending) {
                throw new BusinessException('El comprobante está pendiente de respuesta de ARCA: verificalo antes de anularlo.');
            }

            return $this->states->transition($locked, InvoiceStatus::Voided, 'Anulado: '.$reason);
        });
    }

    private function numberingLock(string $mode, Invoice $invoice): \Illuminate\Contracts\Cache\Lock
    {
        return Cache::lock('arca:numbering:'.$mode.':'.$invoice->point_of_sale.':'.$invoice->voucher_type, 120);
    }

    /** Guarda el intento y el nuevo estado en una transacción mínima. Un CAE obtenido nunca se descarta. */
    private function storeResult(Invoice $pending, ArcaResult $result, string $mode, User $by): Invoice
    {
        try {
            return DB::transaction(function () use ($pending, $result, $mode, $by) {
                $this->recordAttempt($pending, $mode, $result, $by);

                if ($result->uncertain) {
                    Invoice::query()->whereKey($pending->id)->update(['last_error' => $result->error]);
                    ErrorReporter::capture(new \RuntimeException('ARCA sin respuesta para el comprobante #'.$pending->id.' (número '.$result->number.')'), 'arca');

                    return $pending->refresh();
                }

                if (! $result->approved) {
                    $this->states->transition($pending, InvoiceStatus::Rejected, 'Rechazado por ARCA', ['last_error' => $result->error]);

                    return $pending->refresh();
                }

                $this->states->transition($pending, InvoiceStatus::Authorized, 'CAE '.$result->cae, [
                    'number' => $result->number,
                    'cae' => $result->cae,
                    'cae_expires_on' => $result->caeExpiresOn,
                    'last_error' => null,
                ]);

                return $pending->refresh();
            });
        } catch (Throwable $e) {
            if (! $result->approved) {
                throw $e;
            }
            // ARCA ya autorizó: el CAE queda registrado en el log de errores y el comprobante pendiente de verificación.
            $code = ErrorReporter::capture(new \RuntimeException('CAE '.$result->cae.' (número '.$result->number.') obtenido para el comprobante #'
                .$pending->id.' pero no se pudo guardar: '.$e->getMessage(), 0, $e), 'arca');
            Invoice::query()->whereKey($pending->id)->update([
                'last_error' => 'Autorizado por ARCA (CAE '.$result->cae.') pero no se pudo guardar. Usá «Verificar en ARCA». Código '.$code.'.',
            ]);

            throw new BusinessException('ARCA autorizó el comprobante (CAE '.$result->cae.') pero hubo un problema al guardarlo. Quedó pendiente: usá «Verificar en ARCA». Código '.$code.'.');
        }
    }

    private function recordAttempt(Invoice $invoice, string $mode, ArcaResult $result, User $by): void
    {
        ArcaRecord::query()->create([
            'invoice_id' => $invoice->id,
            'mode' => $mode,
            'operation' => $result->operation,
            'status' => $result->approved ? 'success' : ($result->uncertain ? 'uncertain' : 'error'),
            'request' => $this->sanitize($result->request),
            'response' => $this->sanitize($result->response),
            'error_message' => $result->error,
            'attempt' => $invoice->attempts,
            'user_id' => $by->id,
            'created_at' => now(),
        ]);
    }

    /** Efectos posteriores a la autorización. Un error acá se registra pero nunca revierte el CAE. */
    private function afterAuthorized(Invoice $authorized, User $by): void
    {
        try {
            $this->audit->log('invoice', $authorized, null, ['number' => $authorized->formattedNumber(), 'cae' => $authorized->cae],
                'Comprobante autorizado '.$authorized->voucherLabel().' '.$authorized->formattedNumber());

            // Sólo una factura (no una nota de crédito) pasa los cajones despachados a «Facturado».
            if ($authorized->load_id && ! $authorized->isCreditNote()) {
                DB::transaction(function () use ($authorized, $by) {
                    $load = Load::query()->whereKey($authorized->load_id)->lockForUpdate()->first();
                    if ($load && in_array($load->status, [LoadStatus::Dispatched, LoadStatus::Delivered], true)) {
                        $this->loads->markInvoiced($load, $by);
                    }
                });
            }

            InvoiceAuthorized::dispatch($authorized);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Una carga tiene a lo sumo una factura viva (borrador, pendiente o autorizada). Las notas de crédito no cuentan. */
    private function ensureLoadNotInvoiced(Load $load, int $voucherType, ?int $exceptId = null): void
    {
        if (array_key_exists($voucherType, Invoice::CREDIT_NOTE_FOR)) {
            return;
        }
        $existing = Invoice::query()->where('load_id', $load->id)
            ->whereNotIn('voucher_type', array_keys(Invoice::CREDIT_NOTE_FOR))
            ->whereIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Pending->value, InvoiceStatus::Authorized->value])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();
        if ($existing) {
            throw new BusinessException('La carga '.$load->number.' ya tiene el comprobante '.$existing->voucherLabel().' '.$existing->formattedNumber()
                .' ('.$existing->status->label().'). Anulalo o emití una nota de crédito antes de volver a facturarla.');
        }
    }

    /** Nota de crédito: debe ajustar una factura autorizada del mismo cliente y de la misma letra. */
    private function validateAssociation(int $voucherType, Client $client, mixed $associatedId, ?int $selfId = null): ?Invoice
    {
        $expected = Invoice::CREDIT_NOTE_FOR[$voucherType] ?? null;
        if ($expected === null) {
            return null;
        }
        $associated = $associatedId ? Invoice::query()->find($associatedId) : null;
        if (! $associated || $associated->id === $selfId) {
            throw new BusinessException('Una nota de crédito tiene que indicar la factura que ajusta.');
        }
        if ($associated->client_id !== $client->id || (int) $associated->voucher_type !== $expected || $associated->status !== InvoiceStatus::Authorized) {
            throw new BusinessException('La factura asociada debe estar autorizada, ser del mismo cliente y de tipo '.Invoice::VOUCHER_TYPES[$expected].'.');
        }

        return $associated;
    }

    /** Recalcula ítems y totales en el servidor (en centavos; IVA por alícuota sobre la base agrupada). */
    private function syncItems(Invoice $invoice, array $items): void
    {
        $isC = in_array((int) $invoice->voucher_type, [11, 13], true);
        $invoice->items()->delete();
        $lines = [];

        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 2);
            $price = round((float) $item['unit_price'], 4);
            $rate = $isC ? 0.0 : (float) $item['vat_rate'];
            $cents = VatCalculator::lineCents($quantity, $price);
            $lines[] = ['subtotal_cents' => $cents, 'vat_rate' => $rate];

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => mb_substr((string) $item['description'], 0, 255),
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'kg',
                'unit_price' => $price,
                'vat_rate' => $rate,
                'subtotal' => VatCalculator::toAmount($cents),
            ]);
        }

        $totals = VatCalculator::totals(VatCalculator::groups($lines));
        $invoice->forceFill([
            'net_amount' => VatCalculator::toAmount($totals['net']),
            'vat_amount' => VatCalculator::toAmount($totals['vat']),
            'total_amount' => VatCalculator::toAmount($totals['total']),
        ])->save();
    }

    /** Nunca guardar token/sign/certificados en el historial. */
    private function sanitize(array $data): array
    {
        return app(AuditService::class)->sanitize(array_diff_key($data, array_flip(['Token', 'Sign', 'token', 'sign'])));
    }
}
