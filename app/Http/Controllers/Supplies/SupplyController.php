<?php

namespace App\Http\Controllers\Supplies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplies\SupplyMovementRequest;
use App\Http\Requests\Supplies\SupplyRequest;
use App\Models\InventoryMovement;
use App\Models\Provider;
use App\Models\Supply;
use App\Services\SupplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(private readonly SupplyService $supplies)
    {
    }

    public function index(Request $request): View
    {
        $supplies = Supply::query()->with('provider:id,name')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('provider_id'), fn ($q) => $q->where('provider_id', $request->integer('provider_id')))
            ->when($request->boolean('low'), fn ($q) => $q->where('min_stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'))
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('active', true))
            // Stock bajo primero.
            ->orderByRaw('CASE WHEN min_stock > 0 AND stock <= min_stock THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate($this->perPage($request))->withQueryString();

        return view('supplies.index', [
            'supplies' => $supplies,
            'categories' => Supply::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category'),
            'providers' => Provider::query()->orderBy('name')->pluck('name', 'id'),
            'lowCount' => $this->supplies->lowStockCount(),
            'stockValue' => (float) Supply::query()->where('active', true)->selectRaw('COALESCE(SUM(stock * COALESCE(unit_cost, 0)), 0) as v')->value('v'),
            'activeCount' => Supply::query()->where('active', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('supplies.form', $this->formData(new Supply(['unit' => 'u', 'active' => true, 'min_stock' => 0])));
    }

    public function store(SupplyRequest $request): RedirectResponse
    {
        $supply = $this->supplies->create($request->validated());

        return redirect()->route('supplies.show', $supply)->with('success', 'Insumo creado.');
    }

    public function show(Request $request, Supply $supply): View
    {
        $movements = InventoryMovement::query()->where('supply_id', $supply->id)
            ->with(['user:id,first_name,last_name', 'provider:id,name'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest('moved_at')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('supplies.show', [
            'supply' => $supply->load('provider'),
            'movements' => $movements,
            'types' => InventoryMovement::TYPES,
            'providers' => Provider::query()->orderBy('name')->pluck('name', 'id'),
            'low' => SupplyService::isLow($supply),
        ]);
    }

    public function edit(Supply $supply): View
    {
        return view('supplies.form', $this->formData($supply));
    }

    public function update(SupplyRequest $request, Supply $supply): RedirectResponse
    {
        $this->supplies->update($supply, $request->validated());

        return redirect()->route('supplies.show', $supply)->with('success', 'Insumo actualizado.');
    }

    public function storeMovement(SupplyMovementRequest $request, Supply $supply): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $movement = $this->supplies->move($supply, $data['type'], (float) $data['quantity'], $data);
        $supply->refresh();

        $message = sprintf('%s registrado. Stock actual: %s %s.', InventoryMovement::TYPES[$movement->type], num($supply->stock, 2), $supply->unit);
        $low = SupplyService::isLow($supply);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'stock' => (float) $supply->stock, 'low' => $low]);
        }

        $redirect = redirect()->route('supplies.show', $supply)->with('success', $message);

        return $low ? $redirect->with('warning', 'Atención: el stock está en o por debajo del mínimo.') : $redirect;
    }

    private function formData(Supply $supply): array
    {
        return [
            'supply' => $supply,
            'providers' => Provider::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => Supply::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all(),
        ];
    }
}
