<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Models\ProductionStoppage;
use App\Models\Reason;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoppageController extends Controller
{
    use CatalogOptions;

    public function __construct(private readonly ProductionService $production)
    {
    }

    public function index(Request $request): View
    {
        $query = ProductionStoppage::query()
            ->when($request->filled('date_from'), fn ($q) => $q->where('started_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('started_at', '<=', $request->date('date_to')->endOfDay()))
            ->when($request->filled('reason_id'), fn ($q) => $q->where('reason_id', $request->integer('reason_id')))
            ->when($request->filled('production_line_id'), fn ($q) => $q->where('production_line_id', $request->integer('production_line_id')))
            ->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->integer('shift_id')));

        $totalMinutes = (int) (clone $query)->sum('duration_minutes');

        return view('stoppages.index', [
            'open' => ProductionStoppage::query()->whereNull('ended_at')->with('reason', 'productionLine', 'user', 'shift')->orderBy('started_at')->get(),
            'stoppages' => $query->with('reason', 'productionLine', 'user', 'shift')->latest('started_at')
                ->paginate($this->perPage($request))->withQueryString(),
            'totalMinutes' => $totalMinutes,
            'reasons' => Reason::query()->where('type', 'stoppage')->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'allReasons' => Reason::query()->where('type', 'stoppage')->orderBy('name')->pluck('name', 'id'),
            'lines' => $this->lineOptions(),
            'shifts' => $this->shiftOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason_id' => ['required', 'integer', Rule::exists('reasons', 'id')->where('type', 'stoppage')->where('active', true)],
            'production_line_id' => ['nullable', 'integer', Rule::exists('production_lines', 'id')],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['reason_id' => 'motivo', 'production_line_id' => 'línea', 'started_at' => 'inicio', 'notes' => 'observaciones']);

        $this->production->startStoppage($data, $request->user());

        return redirect()->route('stoppages.index')->with('success', 'Parada iniciada.');
    }

    /** Finalizar parada: calcula la duración. */
    public function update(Request $request, ProductionStoppage $stoppage): RedirectResponse
    {
        $data = $request->validate([
            'ended_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['ended_at' => 'fin', 'notes' => 'observaciones']);

        $stoppage = $this->production->finishStoppage($stoppage, $data);

        return redirect()->route('stoppages.index')->with('success', "Parada finalizada: {$stoppage->duration_minutes} min.");
    }
}
