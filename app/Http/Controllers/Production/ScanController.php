<?php

namespace App\Http\Controllers\Production;

use App\Enums\CrateStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Http\Requests\Production\ScanRequest;
use App\Models\Lot;
use App\Services\CrateService;
use App\Services\Devices\DeviceManager;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modo escaneo: pantalla para lector de códigos USB (actúa como teclado).
 * Respuestas JSON uniformes para que la pantalla dé feedback de color y sonido:
 *  - 201/200 {ok: true, record, totals}              registrado (200 = reintento idempotente)
 *  - 409 {ok: false, reason: 'duplicate', message}     cajón ya procesado
 *  - 422 {ok: false, requires_authorization: true}     peso fuera de rango
 *  - 422 {ok: false, reason, message, field}           otro error de negocio
 */
class ScanController extends Controller
{
    use CatalogOptions;

    public function __construct(private readonly ProductionService $production)
    {
    }

    public function show(Request $request): View
    {
        return view('production.scan', $this->screenData($request));
    }

    public function kiosk(Request $request): View
    {
        return view('production.kiosk', $this->screenData($request));
    }

    public function store(ScanRequest $request): JsonResponse
    {
        try {
            $result = $this->production->register($request->validated(), $request->user());
        } catch (BusinessException $e) {
            $status = $e->reason === ProductionService::REASON_DUPLICATE ? 409 : 422;

            return response()->json([
                'ok' => false,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
                'field' => $e->context['field'] ?? null,
                'requires_authorization' => $e->reason === ProductionService::REASON_AUTHORIZATION,
            ], $status);
        }

        $summary = $this->production->operatorSummary($request->user(), 0);

        return response()->json([
            'ok' => true,
            'replay' => $result['replay'],
            'message' => $result['replay'] ? 'Ya estaba registrado (reintento).' : 'Registrado.',
            'record' => $this->production->presentRecord($result['record']),
            'totals' => $summary['totals'],
        ], $result['replay'] ? 200 : 201);
    }

    /** Validación al escanear: estado del cajón o datos del embalador, sin registrar nada. */
    public function lookup(Request $request, CrateService $crates): JsonResponse
    {
        $data = $request->validate([
            'crate' => ['nullable', 'string', 'max:60'],
            'packer' => ['nullable', 'string', 'max:30'],
        ]);

        $response = [];

        if (! empty($data['crate'])) {
            $crate = $crates->findByCode($data['crate'], true);
            if (! $crate) {
                $autoCreate = (bool) setting('production.auto_create_crate', true);
                $response['crate'] = [
                    'exists' => false,
                    'ok' => $autoCreate,
                    'reason' => $autoCreate ? 'new' : 'crate_not_found',
                    'message' => $autoCreate ? 'Cajón nuevo: se dará de alta al registrar.' : 'El cajón no existe.',
                ];
            } else {
                $crate->loadMissing('lot:id,code', 'packer:id,code', 'variety:id,name', 'pallet:id,code');
                $reason = match (true) {
                    $crate->trashed() => 'crate_deleted',
                    $crate->status === CrateStatus::Voided => 'crate_voided',
                    $crate->status !== CrateStatus::Registered => ProductionService::REASON_DUPLICATE,
                    default => 'ok',
                };
                $response['crate'] = [
                    'exists' => true,
                    'ok' => $reason === 'ok',
                    'reason' => $reason,
                    'code' => $crate->code,
                    'status' => $crate->status->value,
                    'status_label' => $crate->status->label(),
                    'lot' => $crate->lot?->code,
                    'lot_id' => $crate->lot_id,
                    'pallet' => $crate->pallet?->code,
                    'message' => match ($reason) {
                        'crate_deleted' => 'El cajón fue eliminado del sistema.',
                        'crate_voided' => "El cajón {$crate->code} está ANULADO.",
                        ProductionService::REASON_DUPLICATE => "DUPLICADO: el cajón {$crate->code} ya fue registrado"
                            .($crate->processed_at ? ' el '.fdate($crate->processed_at, true) : '')
                            .($crate->packer ? ' (embalador '.$crate->packer->code.')' : '').'.',
                        default => 'Cajón '.$crate->code.($crate->lot ? ' · Lote '.$crate->lot->code : ''),
                    },
                ];
            }
        }

        if (! empty($data['packer'])) {
            try {
                $packer = $this->production->resolvePacker($data['packer']);
                $response['packer'] = ['ok' => true, 'id' => $packer->id, 'code' => $packer->code, 'name' => $packer->full_name, 'message' => $packer->full_name];
            } catch (BusinessException $e) {
                $response['packer'] = ['ok' => false, 'reason' => $e->reason, 'message' => $e->getMessage()];
            }
        }

        return response()->json($response);
    }

    /** Última lectura de la balanza de la estación (la publica un puente local por API). */
    public function scale(Request $request, DeviceManager $devices): JsonResponse
    {
        $data = $request->validate(['station' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-\.]+$/']]);
        $reader = $devices->scale();

        return response()->json([
            'driver' => $reader->driver(),
            'station' => $data['station'] ?? 'default',
            'reading' => $reader->read($data['station'] ?? 'default'),
        ]);
    }

    private function screenData(Request $request): array
    {
        $summary = $this->production->operatorSummary($request->user());
        $requireLot = (bool) setting('production.require_lot', false);

        return [
            'config' => [
                'storeUrl' => route('production.scan.store'),
                'lookupUrl' => route('production.scan.lookup'),
                'scaleUrl' => route('production.scan.scale'),
                'scaleDriver' => app(DeviceManager::class)->scale()->driver(),
                'weightMin' => (float) setting('production.weight_min', 0),
                'weightMax' => (float) setting('production.weight_max', 0),
                'requireLot' => $requireLot,
                'userId' => $request->user()->id,
                'shift' => $summary['shift'],
                'totals' => $summary['totals'],
                'recent' => $summary['recent'],
            ],
            'varieties' => $this->varietyOptions(),
            'sizes' => $this->sizeOptions(),
            'lines' => $this->lineOptions(),
            'lots' => Lot::query()->where('status', 'open')->with('producer:id,name')->latest('date')->limit(100)->get()
                ->mapWithKeys(fn (Lot $l) => [$l->id => $l->code.($l->producer ? ' — '.$l->producer->name : '')]),
        ];
    }
}
