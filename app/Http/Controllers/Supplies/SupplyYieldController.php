<?php

namespace App\Http\Controllers\Supplies;

use App\Http\Controllers\Controller;
use App\Models\Supply;
use App\Models\SupplyYield;
use App\Services\SupplyYieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Rendimiento de cera e insumos (antes: hoja «RENDIMIENTO CERA»): desde, hasta y bultos empacados. */
class SupplyYieldController extends Controller
{
    public function __construct(private readonly SupplyYieldService $yields)
    {
    }

    public function index(Request $request): View
    {
        return view('yields.index', [
            'yields' => SupplyYield::query()->with('supply')->orderByDesc('started_on')->orderByDesc('id')
                ->paginate($this->perPage($request))->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('yields.form', $this->formData(new SupplyYield(['started_on' => today(), 'name' => 'Tambor de cera'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->yields->save(null, $this->validated($request), $request->user());

        return redirect()->route('yields.index')->with('success', 'Rendimiento registrado.');
    }

    public function edit(SupplyYield $yield): View
    {
        return view('yields.form', $this->formData($yield));
    }

    public function update(Request $request, SupplyYield $yield): RedirectResponse
    {
        $this->yields->save($yield, $this->validated($request), $request->user(), $request->input('reason'));

        return redirect()->route('yields.index')->with('success', 'Rendimiento actualizado.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['quantity_used' => parse_number($request->input('quantity_used'))]);

        return $request->validate([
            'supply_id' => ['nullable', 'integer', 'exists:supplies,id'],
            'name' => ['required', 'string', 'max:120'],
            'started_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'quantity_used' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'packages_manual' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => 'nombre', 'started_on' => 'desde', 'ended_on' => 'hasta', 'quantity_used' => 'cantidad usada', 'packages_manual' => 'bultos']);
    }

    private function formData(SupplyYield $yield): array
    {
        return ['yield' => $yield, 'supplies' => Supply::query()->where('active', true)->orderBy('name')->pluck('name', 'id')];
    }
}
