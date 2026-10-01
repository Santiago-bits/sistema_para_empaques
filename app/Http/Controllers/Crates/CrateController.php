<?php

namespace App\Http\Controllers\Crates;

use App\Enums\CrateStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Http\Requests\Crates\CrateRequest;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\CrateService;
use App\Services\ProductionService;
use App\Services\SequenceService;
use App\Services\TraceabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CrateController extends Controller
{
    use CatalogOptions;

    public function __construct(private readonly CrateService $crates)
    {
    }

    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        $totals = (clone $query)->toBase()->reorder()
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

        $crates = $query
            ->with('variety:id,name', 'size:id,name', 'packer:id,code,first_name,last_name', 'lot:id,code',
                'pallet:id,code', 'producer:id,name', 'owner:id,name')
            ->latest('created_at')->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('crates.index', [
            'crates' => $crates,
            'totals' => $totals,
            'statuses' => CrateStatus::options(),
            'qualities' => Crate::QUALITY_STATUSES,
            'varieties' => $this->varietyOptions(false),
            'sizes' => $this->sizeOptions(false),
            'packers' => $this->packerOptions(),
            'producers' => $this->producerOptions(),
            'owners' => $this->ownerOptions(),
            'lots' => $this->lotOptions(),
        ]);
    }

    public function create(Request $request, SequenceService $sequences): View
    {
        $crate = new Crate(['pallet_id' => $request->integer('pallet_id') ?: null]);

        return view('crates.form', $this->formData($crate, $sequences->peek('crate')));
    }

    public function store(CrateRequest $request, ProductionService $production): RedirectResponse
    {
        $data = $request->validated();

        $crate = DB::transaction(function () use ($data, $request, $production) {
            $crate = $this->crates->create($data, $request->user());

            // Opcional: registrar la producción en el mismo paso (mismas validaciones que el escaneo).
            if (! empty($data['process'])) {
                $packer = \App\Models\Packer::query()->find($data['packer_id'] ?? null);
                $production->register([
                    'crate_code' => $crate->code,
                    'packer_code' => (string) $packer?->code,
                    'weight' => $data['weight'] ?? 0,
                    'variety_id' => $data['variety_id'] ?? null,
                    'size_id' => $data['size_id'] ?? null,
                    'lot_id' => $data['lot_id'] ?? null,
                    'notes' => 'Alta manual',
                ], $request->user());
            }

            return $crate->refresh();
        });

        $redirect = $request->boolean('another')
            ? redirect()->route('crates.create', array_filter(['pallet_id' => $crate->pallet_id]))
            : redirect()->route('crates.show', $crate);

        return $redirect->with('success', "Cajón {$crate->code} registrado.");
    }

    public function show(Crate $crate, TraceabilityService $traceability): View
    {
        return view('crates.show', array_merge($traceability->forCrate($crate), [
            'locked' => $this->crates->isLocked($crate),
        ]));
    }

    public function edit(Crate $crate): View|RedirectResponse
    {
        if ($this->crates->isLocked($crate)) {
            return redirect()->route('crates.show', $crate)
                ->with('error', 'El cajón está '.mb_strtolower($crate->status->label()).' y ya no se puede modificar.');
        }

        return view('crates.form', $this->formData($crate));
    }

    public function update(CrateRequest $request, Crate $crate): RedirectResponse
    {
        $data = $request->validated();
        $this->crates->update($crate, $data, $data['reason'] ?? null);

        return redirect()->route('crates.show', $crate)->with('success', 'Cajón actualizado.');
    }

    public function void(Request $request, Crate $crate): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->crates->void($crate, $data['reason'], $request->user());

        return redirect()->route('crates.show', $crate)->with('success', "Cajón {$crate->code} anulado.");
    }

    /** Consulta con todos los filtros del índice (también la usan las etiquetas). */
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('code'), function ($q) use ($request) {
                $term = trim((string) $request->string('code'));
                $q->where(fn ($w) => $w->where('code', 'like', $term.'%')->orWhere('barcode', $term));
            })
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->date('date_to')->endOfDay()))
            ->when($request->filled('processed_from'), fn ($q) => $q->where('processed_at', '>=', $request->date('processed_from')->startOfDay()))
            ->when($request->filled('processed_to'), fn ($q) => $q->where('processed_at', '<=', $request->date('processed_to')->endOfDay()))
            ->when($request->filled('variety_id'), fn ($q) => $q->where('variety_id', $request->integer('variety_id')))
            ->when($request->filled('size_id'), fn ($q) => $q->where('size_id', $request->integer('size_id')))
            ->when($request->filled('packer_id'), fn ($q) => $q->where('packer_id', $request->integer('packer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('quality_status'), fn ($q) => $q->where('quality_status', $request->string('quality_status')))
            ->when($request->filled('lot_id'), fn ($q) => $q->where('lot_id', $request->integer('lot_id')))
            ->when($request->filled('producer_id'), fn ($q) => $q->where('producer_id', $request->integer('producer_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('pallet'), function ($q) use ($request) {
                $q->whereIn('pallet_id', Pallet::query()->select('id')->where('code', trim((string) $request->string('pallet'))));
            })
            ->when($request->filled('pallet_id'), fn ($q) => $q->where('pallet_id', $request->integer('pallet_id')))
            ->when($request->filled('load'), function ($q) use ($request) {
                $q->whereIn('current_load_id', DB::table('loads')->select('id')->where('number', trim((string) $request->string('load'))));
            });
    }

    private function filtered(Request $request): Builder
    {
        return self::applyFilters(Crate::query(), $request);
    }

    private function formData(Crate $crate, ?string $nextCode = null): array
    {
        return [
            'crate' => $crate,
            'nextCode' => $nextCode,
            'modes' => $this->crates->fieldModes(),
            'pallets' => Pallet::query()->whereNotIn('status', ['voided', 'dispatched'])->latest('received_at')->limit(300)->pluck('code', 'id'),
            'lots' => $this->lotOptions(),
            'varieties' => $this->varietyOptions(),
            'sizes' => $this->sizeOptions(),
            'packers' => $this->packerOptions(true),
            'locations' => $this->locationOptions(),
        ];
    }
}
