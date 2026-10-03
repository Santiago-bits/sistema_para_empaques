<?php

namespace App\Http\Controllers\Locations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\MoveRequest;
use App\Models\Crate;
use App\Models\LocationMovement;
use App\Models\Pallet;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\LocationService;
use App\Support\CurrentWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function __construct(private readonly LocationService $locations)
    {
    }

    /** Pantalla de movimiento rápido: escanear pallet/cajón → destino → confirmar. */
    public function create(Request $request): View
    {
        $locations = WarehouseLocation::query()->where('warehouse_id', CurrentWarehouse::id())->where('active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'type']);

        return view('locations.move', [
            'locations' => $locations->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name]),
            'initialCode' => (string) $request->string('code'),
            'recent' => LocationMovement::query()->where('user_id', $request->user()->id)
                ->with(['movable', 'fromLocation:id,name', 'toLocation:id,name'])
                ->latest('moved_at')->latest('id')->limit(10)->get(),
        ]);
    }

    /** Búsqueda por código: pallet, cajón o ubicación (para la pantalla de movimiento). */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:60'], 'kind' => ['nullable', 'in:movable,location']]);
        $code = (string) $request->string('code');

        if ($request->input('kind') !== 'location' && ($found = $this->locations->findMovable($code))) {
            /** @var Pallet|Crate $model */
            $model = $found['model'];
            $model->load('location');

            return response()->json([
                'kind' => 'movable',
                'type' => $found['type'],
                'type_label' => $found['type'] === 'pallet' ? 'Pallet' : 'Cajón',
                'id' => $model->id,
                'code' => $model->code,
                'status' => $model->status->label(),
                'location_id' => $model->location_id,
                'location' => $model->location?->path() ?? 'Sin ubicación',
                'crates' => $model instanceof Pallet ? $model->crates()->count() : null,
            ]);
        }

        if ($request->input('kind') !== 'movable' && ($location = $this->locations->findLocation($code))) {
            $stats = $this->locations->occupancy($location->warehouse_id)[$location->id] ?? [];

            return response()->json([
                'kind' => 'location',
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'path' => $location->path(),
                'active' => $location->active,
                'pallets' => $stats['total'] ?? 0,
                'capacity' => $stats['capacity'] ?? 0,
                'free' => $stats['free'] ?? 0,
            ]);
        }

        return response()->json(['message' => 'No se encontró ningún pallet, cajón ni ubicación con ese código.'], 404);
    }

    public function store(MoveRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $movable = $data['movable_type'] === 'pallet'
            ? Pallet::query()->findOrFail($data['movable_id'])
            : Crate::query()->findOrFail($data['movable_id']);
        $to = ! empty($data['to_location_id']) ? WarehouseLocation::query()->findOrFail($data['to_location_id']) : null;

        $movement = $this->locations->move($movable, $to, $data['to_label'] ?? null, $data['notes'] ?? null);
        $movement->load(['fromLocation:id,name', 'toLocation:id,name']);

        $message = sprintf('%s %s movido: %s → %s.',
            $data['movable_type'] === 'pallet' ? 'Pallet' : 'Cajón',
            $movable->code,
            $movement->fromLocation?->name ?? 'Sin ubicación',
            $movement->toLocation?->name ?? $movement->to_label,
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'movement_id' => $movement->id]);
        }

        return back()->with('success', $message);
    }

    /** Informe de movimientos por pallet/cajón con filtros. */
    public function index(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $code = trim((string) $request->string('code'));
        $target = $code !== '' ? $this->locations->findMovable($code) : null;

        $query = LocationMovement::query()
            ->with(['movable', 'fromLocation:id,name', 'toLocation:id,name', 'user:id,first_name,last_name'])
            ->when($request->filled('type'), fn ($q) => $q->where('movable_type', $request->input('type') === 'crate' ? 'crate' : 'pallet'))
            ->when($code !== '', function (Builder $q) use ($target) {
                if ($target) {
                    $q->where('movable_type', $target['model']->getMorphClass())->where('movable_id', $target['model']->getKey());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when($request->filled('location_id'), fn ($q) => $q->where(fn ($w) => $w
                ->where('from_location_id', $request->integer('location_id'))->orWhere('to_location_id', $request->integer('location_id'))))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('moved_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('moved_at', '<=', $request->date('to')->endOfDay()));

        // Recorrido completo del pallet/cajón buscado: "09:30 Cámara 1 → 12:15 Sector B → ...".
        $journey = $target
            ? LocationMovement::query()->where('movable_type', $target['model']->getMorphClass())
                ->where('movable_id', $target['model']->getKey())
                ->with(['fromLocation:id,name', 'toLocation:id,name', 'user:id,first_name,last_name'])
                ->orderBy('moved_at')->orderBy('id')->limit(500)->get()
            : null;

        return view('locations.movements', [
            'movements' => $query->latest('moved_at')->latest('id')->paginate($this->perPage($request))->withQueryString(),
            'target' => $target,
            'journey' => $journey,
            'code' => $code,
            'locations' => WarehouseLocation::query()->where('warehouse_id', CurrentWarehouse::id())->orderBy('code')->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name]),
            'users' => User::query()->visibleTo()->whereIn('id', LocationMovement::query()->select('user_id')->whereNotNull('user_id')->distinct())
                ->orderBy('last_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->full_name]),
        ]);
    }
}
