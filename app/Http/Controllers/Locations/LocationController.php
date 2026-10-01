<?php

namespace App\Http\Controllers\Locations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\LocationRequest;
use App\Models\Crate;
use App\Models\LocationMovement;
use App\Models\Pallet;
use App\Models\WarehouseLocation;
use App\Services\LocationService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private readonly LocationService $locations)
    {
    }

    public function index(Request $request): View
    {
        $warehouseId = CurrentWarehouse::id();
        $all = WarehouseLocation::query()->where('warehouse_id', $warehouseId)
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('active', true))
            ->orderBy('code')->get();

        $search = trim((string) $request->string('q'));
        $matches = $search === '' ? null : $all->filter(fn ($l) => str_contains(mb_strtolower($l->code.' '.$l->name), mb_strtolower($search)))->pluck('id');

        return view('locations.index', [
            'tree' => $all->groupBy(fn ($l) => $l->parent_id ?? 0),
            'matches' => $matches,
            'occupancy' => $this->locations->occupancy($warehouseId),
            'capacity' => $this->locations->capacitySummary($warehouseId),
            'inventory' => $this->locations->palletInventory($warehouseId),
            'types' => WarehouseLocation::TYPES,
            'total' => $all->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('locations.form', $this->formData(new WarehouseLocation([
            'parent_id' => $request->integer('parent_id') ?: null,
            'type' => 'position',
            'capacity_pallets' => 0,
            'active' => true,
        ])));
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = WarehouseLocation::query()->create($request->validated() + ['warehouse_id' => CurrentWarehouse::id()]);

        return redirect()->route('locations.show', $location)->with('success', 'Ubicación creada.');
    }

    public function show(Request $request, WarehouseLocation $location): View
    {
        $ids = $this->locations->descendantIds($location);
        $occupancy = $this->locations->occupancy($location->warehouse_id);

        $pallets = Pallet::query()->whereIn('location_id', $ids)->whereIn('status', LocationService::PALLET_PRESENT)
            ->with(['variety:id,name', 'lot:id,code', 'location:id,name'])
            ->withCount('crates')
            ->orderBy('code')
            ->paginate($this->perPage($request), ['*'], 'pallets_page')
            ->withQueryString();

        $crates = Crate::query()->whereIn('location_id', $ids)->whereNull('pallet_id')
            ->whereNotIn('status', ['dispatched', 'invoiced', 'voided'])
            ->with(['variety:id,name', 'size:id,name', 'location:id,name'])
            ->orderBy('code')
            ->paginate($this->perPage($request), ['*'], 'crates_page')
            ->withQueryString();

        $movements = LocationMovement::query()
            ->where(fn ($q) => $q->where('from_location_id', $location->id)->orWhere('to_location_id', $location->id))
            ->with(['movable', 'fromLocation:id,name', 'toLocation:id,name', 'user:id,first_name,last_name'])
            ->latest('moved_at')->latest('id')->limit(15)->get();

        return view('locations.show', [
            'location' => $location->load(['parent', 'children' => fn ($q) => $q->orderBy('code')]),
            'stats' => $occupancy[$location->id] ?? ['direct' => 0, 'total' => 0, 'capacity' => 0, 'crates' => 0, 'free' => 0, 'pct' => 0],
            'occupancy' => $occupancy,
            'pallets' => $pallets,
            'crates' => $crates,
            'movements' => $movements,
        ]);
    }

    public function edit(WarehouseLocation $location): View
    {
        return view('locations.form', $this->formData($location));
    }

    public function update(LocationRequest $request, WarehouseLocation $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('locations.show', $location)->with('success', 'Ubicación actualizada.');
    }

    /** Mapa del galpón: grilla con sectores, cámaras y zonas coloreados por ocupación. */
    public function map(): View
    {
        $warehouseId = CurrentWarehouse::id();
        $occupancy = $this->locations->occupancy($warehouseId);
        $locations = WarehouseLocation::query()->where('warehouse_id', $warehouseId)->where('active', true)
            ->orderBy('code')->get(['id', 'parent_id', 'type', 'code', 'name', 'capacity_pallets', 'map_x', 'map_y', 'map_w', 'map_h']);

        $items = $locations->map(fn (WarehouseLocation $l) => [
            'id' => $l->id,
            'parent_id' => $l->parent_id,
            'code' => $l->code,
            'name' => $l->name,
            'type' => $l->type,
            'type_label' => WarehouseLocation::TYPES[$l->type] ?? $l->type,
            'x' => $l->map_x,
            'y' => $l->map_y,
            'w' => $l->map_w ?: 2,
            'h' => $l->map_h ?: 2,
            'pallets' => $occupancy[$l->id]['total'] ?? 0,
            'capacity' => $occupancy[$l->id]['capacity'] ?? 0,
            'free' => $occupancy[$l->id]['free'] ?? 0,
            'pct' => $occupancy[$l->id]['pct'] ?? 0,
            'crates' => $occupancy[$l->id]['crates'] ?? 0,
            'url' => route('locations.show', $l),
        ])->values();

        $cols = max(24, (int) $locations->max(fn ($l) => ($l->map_x ?? 0) + ($l->map_w ?: 2) - 1));
        $rows = max(12, (int) $locations->max(fn ($l) => ($l->map_y ?? 0) + ($l->map_h ?: 2) - 1));

        return view('locations.map', [
            'items' => $items,
            'cols' => $cols,
            'rows' => $rows,
            'capacity' => $this->locations->capacitySummary($warehouseId),
            'inventory' => $this->locations->palletInventory($warehouseId),
        ]);
    }

    /** Guarda las posiciones del editor de mapa. */
    public function updateMap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'positions' => ['required', 'array', 'max:2000'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.x' => ['nullable', 'integer', 'min:1', 'max:200'],
            'positions.*.y' => ['nullable', 'integer', 'min:1', 'max:200'],
            'positions.*.w' => ['nullable', 'integer', 'min:1', 'max:200'],
            'positions.*.h' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $warehouseId = CurrentWarehouse::id();
        $updated = 0;
        DB::transaction(function () use ($data, $warehouseId, &$updated) {
            $locations = WarehouseLocation::query()->where('warehouse_id', $warehouseId)
                ->whereIn('id', array_column($data['positions'], 'id'))->get()->keyBy('id');
            foreach ($data['positions'] as $pos) {
                $location = $locations[$pos['id']] ?? null;
                if (! $location) {
                    continue;
                }
                $placed = ! empty($pos['x']) && ! empty($pos['y']);
                $location->fill([
                    'map_x' => $placed ? $pos['x'] : null,
                    'map_y' => $placed ? $pos['y'] : null,
                    'map_w' => $placed ? ($pos['w'] ?? 2) : null,
                    'map_h' => $placed ? ($pos['h'] ?? 2) : null,
                ]);
                if ($location->isDirty()) {
                    $location->save();
                    $updated++;
                }
            }
        });

        return response()->json(['message' => $updated ? "Mapa guardado ({$updated} ubicaciones actualizadas)." : 'No había cambios para guardar.']);
    }

    /** Contenido de una ubicación (panel lateral del mapa). */
    public function content(WarehouseLocation $location): JsonResponse
    {
        $ids = $this->locations->descendantIds($location);
        $stats = $this->locations->occupancy($location->warehouse_id)[$location->id] ?? [];

        $pallets = Pallet::query()->whereIn('location_id', $ids)->whereIn('status', LocationService::PALLET_PRESENT)
            ->with(['variety:id,name', 'location:id,name'])->withCount('crates')->orderBy('code')->limit(100)->get()
            ->map(fn (Pallet $p) => [
                'code' => $p->code, 'status' => $p->status->label(), 'variety' => $p->variety?->name,
                'crates' => $p->crates_count, 'location' => $p->location?->name,
            ]);
        $crates = Crate::query()->whereIn('location_id', $ids)->whereNull('pallet_id')
            ->whereNotIn('status', ['dispatched', 'invoiced', 'voided'])
            ->with('variety:id,name')->orderBy('code')->limit(100)->get()
            ->map(fn (Crate $c) => ['code' => $c->code, 'status' => $c->status->label(), 'variety' => $c->variety?->name]);

        return response()->json([
            'id' => $location->id,
            'name' => $location->name,
            'path' => $location->path(),
            'type' => WarehouseLocation::TYPES[$location->type] ?? $location->type,
            'stats' => $stats,
            'pallets' => $pallets,
            'crates' => $crates,
            'url' => route('locations.show', $location),
        ]);
    }

    private function formData(WarehouseLocation $location): array
    {
        $exclude = $location->exists ? $this->locations->descendantIds($location)->all() : [];

        return [
            'location' => $location,
            'types' => WarehouseLocation::TYPES,
            'parents' => WarehouseLocation::query()->where('warehouse_id', $location->warehouse_id ?? CurrentWarehouse::id())
                ->whereNotIn('id', $exclude)->orderBy('code')->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name]),
        ];
    }
}
