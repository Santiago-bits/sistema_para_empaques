<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\ContainerMovementRequest;
use App\Models\AccountMovement;
use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Services\ContainerMovementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ficha de movimientos de un cliente, productor, camionero, transportista, proveedor o empleado. */
class HolderActivityController extends Controller
{
    /** Ficha del catálogo de cada titular (para volver). */
    public const CATALOG_ROUTES = [
        'client' => 'catalogs.clients.show', 'producer' => 'catalogs.producers.show', 'driver' => 'catalogs.drivers.show',
        'transporter' => 'catalogs.transporters.show', 'provider' => 'catalogs.providers.show', 'employee' => 'catalogs.employees.show',
    ];

    public const KINDS = ['money' => 'Plata', 'containers' => 'Bines y envases', 'lots' => 'Fruta que ingresó', 'loads' => 'Cargas'];

    public function __construct(private readonly ContainerMovementService $containers)
    {
    }

    public function show(Request $request, string $type, int $holder): View
    {
        $model = $this->holder($type, $holder);
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys(self::KINDS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        // La plata sólo la ve quien puede ver tesorería.
        $kind = $data['kind'] ?? null;
        $canMoney = $request->user()->can('treasury.view');
        if ($kind === 'money' && ! $canMoney) {
            abort(403);
        }

        $query = $this->containers->activity($model, $kind, $data['from'] ?? null, $data['to'] ?? null)
            ->when(! $canMoney, fn ($q) => $q->where('kind', '!=', 'money'));
        $summary = $this->containers->summary($model);
        if (! $canMoney) {
            $summary['balance'] = null;
        }

        return view('catalogs.activity', [
            'type' => $type,
            'holder' => $model,
            'name' => AccountMovement::holderLabel($model),
            'typeLabel' => ContainerMovement::HOLDERS[$type][0],
            'backUrl' => route(self::CATALOG_ROUTES[$type], $model->getKey()),
            'summary' => $summary,
            'movements' => $query->paginate($this->perPage($request))->withQueryString(),
            'kinds' => array_filter(self::KINDS, fn ($k) => $k !== 'money' || $canMoney, ARRAY_FILTER_USE_KEY),
            'containerTypes' => ContainerType::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'canPayWithContainers' => array_key_exists($type, AccountMovement::HOLDERS) && module_enabled('treasury'),
        ]);
    }

    public function store(ContainerMovementRequest $request, string $type, int $holder): RedirectResponse
    {
        $model = $this->holder($type, $holder);
        $movement = $this->containers->register($model, $request->validated(), $request->user());

        return back()->with('success', 'Listo: '.$movement->description.'.');
    }

    public function void(Request $request, string $type, int $holder, ContainerMovement $movement): RedirectResponse
    {
        $model = $this->holder($type, $holder);
        abort_unless($movement->holder_type === $model->getMorphClass() && (int) $movement->holder_id === (int) $model->getKey(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], [], ['reason' => 'motivo']);
        $this->containers->void($movement, $data['reason'], $request->user());

        return back()->with('success', 'Movimiento anulado.');
    }

    private function holder(string $type, int $id): Model
    {
        $class = ContainerMovement::holderClass($type);

        return $class::query()->withTrashed()->findOrFail($id);
    }
}
