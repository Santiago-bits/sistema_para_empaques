<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\LotRequest;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Owner;
use App\Models\Pallet;
use App\Models\Producer;
use App\Models\Season;
use App\Models\Variety;
use App\Services\LotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LotController extends Controller
{
    public function __construct(private readonly LotService $lots)
    {
    }

    public function index(Request $request): View
    {
        $lots = Lot::query()
            ->with(['producer:id,name', 'owner:id,name', 'variety:id,name'])
            ->withCount('crates')
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', trim((string) $request->query('q')).'%'))
            ->when($request->filled('producer_id'), fn ($q) => $q->where('producer_id', $request->integer('producer_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('variety_id'), fn ($q) => $q->where('variety_id', $request->integer('variety_id')))
            ->when($request->filled('status') && array_key_exists($request->query('status'), Lot::STATUSES),
                fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('lots.index', ['lots' => $lots] + $this->options());
    }

    public function create(): View
    {
        return view('lots.form', [
            'lot' => new Lot(['date' => today(), 'season_id' => Season::current()?->id]),
            'nextCode' => $this->lots->nextCode(),
        ] + $this->options());
    }

    public function store(LotRequest $request): RedirectResponse
    {
        $lot = $this->lots->create($request->safe()->except('reason'));

        return redirect()->route('lots.show', $lot)->with('success', "Lote {$lot->code} creado.");
    }

    public function show(Lot $lot): View
    {
        $lot->load(['producer', 'owner', 'variety', 'season', 'stateHistories.user', 'creator', 'containerType']);

        return view('lots.show', [
            'lot' => $lot,
            'summary' => $this->lots->summary($lot),
            'pallets' => Pallet::query()->where('lot_id', $lot->id)->with('variety:id,name')->withCount('crates')
                ->latest('received_at')->paginate(10, ['*'], 'pallets_page'),
            'crates' => Crate::query()->where('lot_id', $lot->id)->with(['variety:id,name', 'size:id,name', 'packer:id,code,first_name,last_name'])
                ->latest('id')->paginate(15, ['*'], 'crates_page'),
        ]);
    }

    /** Planilla de romaneo del lote (imprimible). */
    public function romaneo(Lot $lot): View
    {
        $lot->load(['producer', 'owner', 'variety', 'season', 'containerType']);

        return view('lots.romaneo', ['lot' => $lot] + $this->lots->romaneo($lot));
    }

    public function edit(Lot $lot): View
    {
        abort_if($lot->status === 'voided', 403, 'Un lote anulado no se puede modificar.');

        return view('lots.form', ['lot' => $lot, 'nextCode' => null] + $this->options());
    }

    public function update(LotRequest $request, Lot $lot): RedirectResponse
    {
        $this->lots->update($lot, $request->safe()->except('reason'), $request->validated('reason'));

        return redirect()->route('lots.show', $lot)->with('success', 'Lote actualizado.');
    }

    public function close(Request $request, Lot $lot): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:255']]);
        $this->lots->close($lot, $data['notes'] ?? null);

        return back()->with('success', "Lote {$lot->code} cerrado.");
    }

    public function void(Request $request, Lot $lot): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->lots->void($lot, $data['reason']);

        return back()->with('success', "Lote {$lot->code} anulado.");
    }

    private function options(): array
    {
        return [
            'producers' => Producer::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'owners' => Owner::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'varieties' => Variety::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'seasons' => Season::query()->orderByDesc('starts_on')->pluck('name', 'id'),
            'statuses' => Lot::STATUSES,
        ];
    }
}
