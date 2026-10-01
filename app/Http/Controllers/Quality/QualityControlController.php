<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\QualityControlRequest;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Pallet;
use App\Models\QualityControl;
use App\Models\Reason;
use App\Models\User;
use App\Services\QualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityControlController extends Controller
{
    public function __construct(private readonly QualityService $quality)
    {
    }

    public function index(Request $request): View
    {
        $controls = QualityControl::query()
            ->with(['crate:id,code', 'lot:id,code', 'pallet:id,code', 'user:id,first_name,last_name'])
            ->withCount('rejects')
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->string('result')))
            ->when($request->filled('target'), fn ($q) => match ((string) $request->string('target')) {
                'crate' => $q->whereNotNull('crate_id'),
                'lot' => $q->whereNotNull('lot_id'),
                'pallet' => $q->whereNotNull('pallet_id'),
                default => $q,
            })
            ->when($request->filled('code'), function ($q) use ($request) {
                $code = trim((string) $request->string('code'));
                $q->where(fn ($w) => $w
                    ->whereIn('crate_id', Crate::query()->select('id')->where('code', $code)->orWhere('barcode', $code))
                    ->orWhereIn('pallet_id', Pallet::query()->select('id')->where('code', $code)->orWhere('barcode', $code))
                    ->orWhereIn('lot_id', Lot::query()->select('id')->where('code', $code)));
            })
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('controlled_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('controlled_at', '<=', $request->date('to')->endOfDay()))
            ->latest('controlled_at')->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $today = QualityControl::query()->where('controlled_at', '>=', today())
            ->selectRaw('result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result');

        return view('quality.index', [
            'controls' => $controls,
            'results' => QualityControl::RESULTS,
            'targets' => ['crate' => 'Cajón', 'lot' => 'Lote', 'pallet' => 'Pallet'],
            'users' => User::query()->whereIn('id', QualityControl::query()->select('user_id')->distinct())
                ->orderBy('last_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->full_name]),
            'today' => $today,
        ]);
    }

    public function create(Request $request): View
    {
        return view('quality.create', [
            'reasons' => Reason::query()->where('type', 'reject')->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'results' => QualityControl::RESULTS,
            'initialCode' => (string) $request->string('code'),
        ]);
    }

    /** Búsqueda por código (escaneo) de cajón, pallet o lote para la pantalla rápida de control. */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:60']]);
        $found = $this->quality->findTarget((string) $request->string('code'));

        if (! $found) {
            return response()->json(['message' => 'No se encontró ningún cajón, pallet ni lote con ese código.'], 404);
        }

        return response()->json($this->describe($found['type'], $found['model']));
    }

    public function store(QualityControlRequest $request): JsonResponse|RedirectResponse
    {
        $result = $this->quality->record($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['message'],
                'applied' => $result['applied'],
                'skipped' => $result['skipped'],
                'rejects' => $result['rejects'],
                'control_id' => $result['control']->id,
                'url' => route('quality.show', $result['control']),
            ]);
        }

        return redirect()->route('quality.show', $result['control'])->with('success', $result['message']);
    }

    public function show(QualityControl $qualityControl): View
    {
        $qualityControl->load([
            'crate.variety', 'crate.size', 'crate.packer', 'crate.lot', 'lot.producer', 'lot.variety', 'pallet.variety',
            'pallet.lot', 'user', 'rejects.reason', 'rejects.crate:id,code',
        ]);

        return view('quality.show', ['control' => $qualityControl]);
    }

    private function describe(string $type, Crate|Pallet|Lot $model): array
    {
        if ($model instanceof Crate) {
            $model->load(['variety:id,name', 'size:id,name', 'packer:id,code,first_name,last_name', 'lot:id,code']);

            return [
                'type' => 'crate',
                'type_label' => 'Cajón',
                'id' => $model->id,
                'code' => $model->code,
                'status' => $model->status->value,
                'status_label' => $model->status->label(),
                'quality_status' => Crate::QUALITY_STATUSES[$model->quality_status] ?? $model->quality_status,
                'eligible' => in_array($model->status, QualityService::BULK_ELIGIBLE, true),
                'weight' => $model->weight !== null ? (float) $model->weight : null,
                'details' => array_filter([
                    'Variedad' => $model->variety?->name,
                    'Tamaño' => $model->size?->name,
                    'Lote' => $model->lot?->code,
                    'Embalador' => $model->packer ? $model->packer->code.' — '.$model->packer->full_name : null,
                    'Peso' => $model->weight !== null ? kg($model->weight) : null,
                ], fn ($v) => $v !== null),
            ];
        }

        $fk = $model instanceof Lot ? 'lot_id' : 'pallet_id';
        $crates = Crate::query()->where($fk, $model->id);
        $eligible = (clone $crates)->whereIn('status', array_map(fn ($s) => $s->value, QualityService::BULK_ELIGIBLE))->count();
        $model->load('variety:id,name');

        return [
            'type' => $type,
            'type_label' => $type === 'lot' ? 'Lote' : 'Pallet',
            'id' => $model->id,
            'code' => $model->code,
            'status' => is_object($model->status) ? $model->status->value : $model->status,
            'status_label' => is_object($model->status) ? $model->status->label() : (Lot::STATUSES[$model->status] ?? $model->status),
            'eligible_crates' => $eligible,
            'total_crates' => (clone $crates)->count(),
            'details' => array_filter([
                'Variedad' => $model->variety?->name,
                'Cajones' => num((clone $crates)->count()),
                'Elegibles para aprobar/rechazar' => num($eligible),
            ], fn ($v) => $v !== null),
        ];
    }
}
