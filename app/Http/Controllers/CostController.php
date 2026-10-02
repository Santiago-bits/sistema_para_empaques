<?php

namespace App\Http\Controllers;

use App\Http\Requests\Costs\CostRequest;
use App\Models\Cost;
use App\Models\Load;
use App\Services\CostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CostController extends Controller
{
    public function __construct(private readonly CostService $costs)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(Cost::CATEGORIES))], 'q' => ['nullable', 'string', 'max:100'],
        ]);
        $filters = [
            'from' => $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : today()->startOfMonth(),
            'to' => $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : today()->endOfDay(),
            'category' => $request->query('category'),
            'q' => $request->query('q'),
        ];
        $query = $this->costs->filtered($filters);

        return view('costs.index', [
            'filters' => $filters,
            'costs' => (clone $query)->with('user:id,first_name,last_name', 'costable')->latest('date')->latest('id')
                ->paginate($this->perPage($request))->withQueryString(),
            'totals' => $this->costs->totalsByCategory($query),
            'profit' => $request->user()->can('profit.view') ? $this->costs->profitability($filters['from'], $filters['to']) : null,
        ]);
    }

    public function create(): View
    {
        return view('costs.form', ['cost' => new Cost(['date' => today(), 'category' => 'other']), 'loads' => $this->recentLoads()]);
    }

    public function store(CostRequest $request): RedirectResponse
    {
        $this->costs->save($request->validated(), $request->user());

        return redirect()->route('costs.index')->with('success', 'Costo registrado.');
    }

    public function edit(Cost $cost): View
    {
        return view('costs.form', ['cost' => $cost, 'loads' => $this->recentLoads()]);
    }

    public function update(CostRequest $request, Cost $cost): RedirectResponse
    {
        $this->costs->save($request->validated(), $request->user(), $cost);

        return redirect()->route('costs.index')->with('success', 'Costo actualizado.');
    }

    public function destroy(Cost $cost): RedirectResponse
    {
        $cost->delete();

        return redirect()->route('costs.index')->with('success', 'Costo eliminado (queda registrado en auditoría).');
    }

    /** @return array<int, string> */
    private function recentLoads(): array
    {
        return Load::query()->latest('date')->limit(200)->get(['id', 'number', 'date'])
            ->mapWithKeys(fn (Load $l) => [$l->id => $l->number.' · '.$l->date->format('d/m/Y')])->all();
    }
}
