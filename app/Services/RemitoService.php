<?php

namespace App\Services;

use App\Enums\LoadStatus;
use App\Enums\RemitoStatus;
use App\Events\RemitoIssued;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidTransitionException;
use App\Models\DispatchCheck;
use App\Models\Load;
use App\Models\Remito;
use App\Models\RemitoItem;
use App\Models\User;
use App\Services\Remitos\QrCodeRenderer;
use App\Services\Signatures\CanvasSignatureStore;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remitos: emisión desde una carga cerrada, PDF con QR, consulta pública, entrega con firma y anulación.
 */
class RemitoService
{
    public function __construct(
        private readonly LoadService $loads,
        private readonly StateTransitionService $transitions,
        private readonly AuditService $audit,
        private readonly SequenceService $sequences,
        private readonly QrCodeRenderer $qr,
        private readonly CanvasSignatureStore $signatures,
    ) {
    }

    public function issue(Load $load, User $by, ?string $notes = null): Remito
    {
        $remito = DB::transaction(function () use ($load, $by, $notes) {
            $locked = Load::query()->whereKey($load->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LoadStatus::Closed) {
                throw new BusinessException('Sólo se puede emitir el remito de una carga cerrada (antes del despacho).');
            }
            $existing = Remito::query()->where('active_load_id', $locked->id)->first();
            if ($existing) {
                throw new BusinessException("La carga ya tiene el remito vigente {$existing->number}.");
            }

            $items = $this->loads->byVarietySize($locked);
            $totalCrates = array_sum(array_column($items, 'crates'));
            if ($totalCrates === 0) {
                throw new BusinessException('La carga no tiene cajones.');
            }

            try {
                $remito = Remito::query()->create([
                    'number' => $this->sequences->next('remito'),
                    'load_id' => $locked->id,
                    'active_load_id' => $locked->id,
                    'client_id' => $locked->client_id,
                    'destination_id' => $locked->destination_id,
                    'truck_id' => $locked->truck_id,
                    'driver_id' => $locked->driver_id,
                    'issued_at' => now(),
                    'total_crates' => $totalCrates,
                    'total_kg' => round(array_sum(array_column($items, 'kg')), 2),
                    'status' => RemitoStatus::Issued,
                    'public_token' => Str::random(40),
                    'notes' => $notes,
                    'created_by' => $by->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessException('La carga ya tiene un remito vigente (emitido por otro usuario recién).');
            }

            RemitoItem::query()->insert(array_map(fn ($i) => [
                'remito_id' => $remito->id,
                'variety_id' => $i['variety_id'],
                'size_id' => $i['size_id'],
                'crates' => $i['crates'],
                'kg' => $i['kg'],
            ], $items));

            $this->transitions->recordInitial($remito, RemitoStatus::Issued, 'Remito emitido para la carga '.$locked->number);
            $this->audit->log('issue', $remito, null, ['number' => $remito->number, 'load' => $locked->number],
                'Emitió el remito '.$remito->number.' de la carga '.$locked->number);

            return $remito;
        });

        RemitoIssued::dispatch($remito);

        return $remito;
    }

    public function void(Remito $remito, string $reason, User $by): Remito
    {
        if (trim($reason) === '') {
            throw new BusinessException('Indicá el motivo de la anulación.');
        }

        return DB::transaction(function () use ($remito, $reason, $by) {
            // Mismo orden de bloqueo que el despacho (primero la carga, después el remito): así un
            // despacho y una anulación simultáneos no pueden dejar una carga despachada con el remito anulado.
            $load = Load::query()->whereKey($remito->load_id)->lockForUpdate()->firstOrFail();
            $locked = Remito::query()->whereKey($remito->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== RemitoStatus::Issued) {
                throw new InvalidTransitionException($locked->status->label(), RemitoStatus::Voided->label());
            }
            if (in_array($load->status, [LoadStatus::Dispatched, LoadStatus::Delivered], true)) {
                throw new BusinessException('No se puede anular el remito de una carga ya despachada.');
            }

            $this->transitions->transition($locked, RemitoStatus::Voided, 'Anulado: '.$reason, ['active_load_id' => null]);

            DispatchCheck::query()->where('load_id', $locked->load_id)->where('item', 'remito')
                ->update(['checked' => false, 'user_id' => $by->id, 'checked_at' => now(), 'notes' => 'Remito anulado']);

            $this->audit->log('void', $locked, ['status' => RemitoStatus::Issued->value], ['status' => RemitoStatus::Voided->value],
                'Anuló el remito '.$locked->number, $reason);

            return $locked;
        });
    }

    /**
     * Registra la entrega: receptor, DNI, firma (PNG desde canvas) y observaciones.
     * Remito → Entregado y carga → Entregada.
     *
     * @param  array{delivered_at?: CarbonInterface|string|null, receiver_name: string, receiver_dni: string, signature: string, delivery_notes?: string|null}  $data
     */
    public function deliver(Remito $remito, array $data, User $by): Remito
    {
        $signaturePath = $this->signatures->store($data['signature']);

        try {
            return DB::transaction(function () use ($remito, $data, $by, $signaturePath) {
                $locked = Remito::query()->whereKey($remito->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->status !== RemitoStatus::Issued) {
                    throw new InvalidTransitionException($locked->status->label(), RemitoStatus::Delivered->label());
                }

                $load = Load::query()->findOrFail($locked->load_id);
                $this->loads->markDelivered($load, 'Entregada según remito '.$locked->number);

                $this->transitions->transition($locked, RemitoStatus::Delivered, 'Entrega registrada', [
                    'delivered_at' => $data['delivered_at'] ?? now(),
                    'receiver_name' => $data['receiver_name'],
                    'receiver_dni' => preg_replace('/\D/', '', $data['receiver_dni']),
                    'signature_path' => $signaturePath,
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                ]);

                $this->audit->log('deliver', $locked, null, ['receiver' => $data['receiver_name']],
                    'Registró la entrega del remito '.$locked->number);

                return $locked;
            });
        } catch (\Throwable $e) {
            $this->signatures->delete($signaturePath);
            throw $e;
        }
    }

    /** URL pública (QR) del remito: sólo datos limitados, sin login. */
    public function publicUrl(Remito $remito): string
    {
        return route('remitos.public', $remito->public_token);
    }

    public function qrDataUri(Remito $remito, int $size = 160): string
    {
        return $this->qr->dataUri($this->publicUrl($remito), $size);
    }

    /** PDF del remito (dompdf, QR SVG incrustado). */
    public function pdf(Remito $remito): \Barryvdh\DomPDF\PDF
    {
        $remito->loadMissing(['client', 'destination', 'truck', 'driver', 'items.variety', 'items.size', 'creator']);
        $load = Load::query()->withTrashed()->find($remito->load_id);

        return Pdf::loadView('remitos.pdf', [
            'remito' => $remito,
            'cargo' => $load,
            'qr' => $this->qrDataUri($remito, 140),
            'publicUrl' => $this->publicUrl($remito),
        ])->setPaper('a4');
    }
}
