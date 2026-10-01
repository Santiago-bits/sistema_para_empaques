<?php

namespace App\Http\Controllers\Pallets;

use App\Enums\PalletStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Http\Requests\Pallets\PalletRequest;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\PalletService;
use App\Services\SequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PalletController extends Controller
{
    use CatalogOptions;

    public function __construct(private readonly PalletService $pallets)
    {
    }

    public function index(Request $request): View
    {
        $pallets = Pallet::query()
            ->with('lot:id,code', 'producer:id,name', 'owner:id,name', 'variety:id,name', 'location:id,code,name')
            ->withCount('crates')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->string('q'));
                $q->where(fn ($w) => $w->where('code', 'like', $term.'%')->orWhere('barcode', $term));
            })
            ->when($request->filled('date_from'), fn ($q) => $q->where('received_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('received_at', '<=', $request->date('date_to')->endOfDay()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('producer_id'), fn ($q) => $q->where('producer_id', $request->integer('producer_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('variety_id'), fn ($q) => $q->where('variety_id', $request->integer('variety_id')))
            ->when($request->filled('lot_id'), fn ($q) => $q->where('lot_id', $request->integer('lot_id')))
            ->latest('received_at')->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('pallets.index', [
            'pallets' => $pallets,
            'statuses' => PalletStatus::options(),
            'producers' => $this->producerOptions(),
            'owners' => $this->ownerOptions(),
            'varieties' => $this->varietyOptions(false),
            'lots' => $this->lotOptions(),
        ]);
    }

    public function create(SequenceService $sequences): View
    {
        return view('pallets.form', $this->formData(new Pallet(['received_at' => now()]), $sequences->peek('pallet')));
    }

    public function store(PalletRequest $request): RedirectResponse
    {
        $pallet = $this->pallets->create($request->validated(), $request->user());

        $redirect = $request->boolean('another') ? redirect()->route('pallets.create') : redirect()->route('pallets.show', $pallet);

        return $redirect->with('success', "Pallet {$pallet->code} ingresado.");
    }

    public function show(Pallet $pallet): View
    {
        $pallet->load('lot', 'producer', 'owner', 'variety', 'location', 'creator', 'season',
            'stateHistories.user', 'movements.fromLocation', 'movements.toLocation', 'movements.user');

        $crates = Crate::query()->where('pallet_id', $pallet->id)
            ->with('variety:id,name', 'size:id,name', 'packer:id,code,first_name,last_name')
            ->orderBy('code')->paginate(50, ['*'], 'crates_page')->withQueryString();

        $totals = Crate::query()->where('pallet_id', $pallet->id)
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

        $documents = module_enabled('documents') && method_exists($pallet, 'documents')
            ? $pallet->documents()->latest()->get() : collect();

        return view('pallets.show', compact('pallet', 'crates', 'totals', 'documents'));
    }

    public function edit(Pallet $pallet): View
    {
        return view('pallets.form', $this->formData($pallet));
    }

    public function update(PalletRequest $request, Pallet $pallet): RedirectResponse
    {
        $this->pallets->update($pallet, $request->validated(), $request->user());

        return redirect()->route('pallets.show', $pallet)->with('success', 'Pallet actualizado.');
    }

    public function void(Request $request, Pallet $pallet): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->pallets->void($pallet, $data['reason']);

        return redirect()->route('pallets.show', $pallet)->with('success', "Pallet {$pallet->code} anulado.");
    }

    private function formData(Pallet $pallet, ?string $nextCode = null): array
    {
        return [
            'pallet' => $pallet,
            'nextCode' => $nextCode,
            'lots' => $this->lotOptions(! $pallet->exists),
            'producers' => $this->producerOptions(),
            'owners' => $this->ownerOptions(),
            'varieties' => $this->varietyOptions(),
            'locations' => $this->locationOptions(),
        ];
    }
}
