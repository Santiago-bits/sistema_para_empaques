<?php

namespace App\Http\Controllers\Loads;

use App\Enums\LoadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loads\LoadRequest;
use App\Models\Client;
use App\Models\Crate;
use App\Models\Destination;
use App\Models\DispatchCheck;
use App\Models\Driver;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Owner;
use App\Models\Pallet;
use App\Models\Producer;
use App\Models\Size;
use App\Models\Transporter;
use App\Models\Truck;
use App\Models\Variety;
use App\Services\LoadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cargas: alta, armado inteligente (asignación concurrente segura), cierre con resumen,
 * reapertura, cancelación y checklist de despacho. La lógica vive en LoadService.
 */
class LoadController extends Controller
{
    public function __construct(private readonly LoadService $loads)
    {
    }

    public function index(Request $request): View
    {
        $loads = Load::query()
            ->with(['truck:id,plate', 'driver:id,first_name,last_name', 'destination:id,name', 'client:id,business_name'])
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', trim((string) $request->query('q')).'%'))
            ->when($request->filled('status') && LoadStatus::tryFrom((string) $request->query('status')),
                fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('destination_id'), fn ($q) => $q->where('destination_id', $request->integer('destination_id')))
            ->when($request->filled('truck_id'), fn ($q) => $q->where('truck_id', $request->integer('truck_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->latest('date')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('loads.index', ['loads' => $loads, 'statuses' => LoadStatus::options()] + $this->options());
    }

    public function create(): View
    {
        return view('loads.form', ['load' => new Load(['date' => today()])] + $this->options());
    }

    public function store(LoadRequest $request): RedirectResponse
    {
        $load = $this->loads->create($request->validated(), $request->user());

        return redirect()->route('loads.builder', $load)->with('success', "Carga {$load->number} creada. Agregá los cajones.");
    }

    public function show(Load $load): View
    {
        $load->load(['truck.transporter', 'driver', 'transporter', 'destination', 'client', 'owner', 'creator', 'closer', 'dispatcher',
            'stateHistories.user', 'dispatchChecks.user']);

        return view('loads.show', [
            'load' => $load,
            'summary' => $this->loads->summary($load),
            'remito' => $load->activeRemito()->first() ?? $load->remitos()->latest('id')->first(),
            'invoices' => $load->invoices()->latest('id')->get(),
        ]);
    }

    public function edit(Load $load): View|RedirectResponse
    {
        if (! $load->status->isEditable()) {
            return redirect()->route('loads.show', $load)->with('error', 'Una carga cerrada no se puede modificar. Reabrila si tenés permiso.');
        }

        return view('loads.form', ['load' => $load] + $this->options());
    }

    public function update(LoadRequest $request, Load $load): RedirectResponse
    {
        $this->loads->update($load, $request->validated());

        return redirect()->route('loads.show', $load)->with('success', 'Carga actualizada.');
    }

    /** Pantalla de armado: filtros de cajones disponibles + contenido actual. */
    public function builder(Load $load): View|RedirectResponse
    {
        if (! $load->status->isEditable()) {
            return redirect()->route('loads.show', $load)->with('error', 'La carga no está en armado.');
        }

        return view('loads.builder', [
            'load' => $load->load(['truck', 'destination', 'client']),
            'config' => [
                'loadId' => $load->id,
                'availableUrl' => route('loads.available', $load),
                'contentUrl' => route('loads.content', $load),
                'assignUrl' => route('loads.crates.assign', $load),
                'removeUrl' => route('loads.crates.remove', $load),
                'closeUrl' => route('loads.close.show', $load),
                'showUrl' => route('loads.show', $load),
            ],
            'varieties' => Variety::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'sizes' => Size::query()->where('active', true)->orderBy('sort')->pluck('name', 'id'),
            'lots' => Lot::query()->where('status', '!=', 'voided')->latest('date')->limit(300)->pluck('code', 'id'),
            'producers' => Producer::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'owners' => Owner::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Cajones disponibles (JSON paginado) según filtros. */
    public function available(Request $request, Load $load): JsonResponse
    {
        $filters = $this->filters($request);
        $page = $this->loads->availableQuery($load, $filters)
            ->with(['variety:id,name', 'size:id,name', 'lot:id,code', 'pallet:id,code'])
            ->paginate(min(100, max(10, $request->integer('per_page', 50))), ['id', 'code', 'variety_id', 'size_id', 'lot_id', 'pallet_id', 'weight', 'status', 'processed_at']);

        $totals = $this->loads->availableQuery($load, $filters)->reorder()->toBase()
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Crate $c) => [
                'id' => $c->id, 'code' => $c->code, 'variety' => $c->variety?->name, 'size' => $c->size?->name,
                'lot' => $c->lot?->code, 'pallet' => $c->pallet?->code, 'weight' => (float) $c->weight, 'status' => $c->status->label(),
            ]),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => (int) $totals->crates,
            'kg' => round((float) $totals->kg, 2),
        ]);
    }

    /** Contenido actual de la carga (JSON) para refresco en vivo. */
    public function content(Request $request, Load $load): JsonResponse
    {
        $load->refresh();
        $crates = Crate::query()->where('current_load_id', $load->id)
            ->with(['variety:id,name', 'size:id,name', 'pallet:id,code'])
            ->latest('updated_at')->latest('id')
            ->paginate(100, ['id', 'code', 'variety_id', 'size_id', 'pallet_id', 'weight']);

        return response()->json([
            'status' => $load->status->value,
            'version' => $load->version,
            'total_crates' => $load->total_crates,
            'total_kg' => (float) $load->total_kg,
            'planned' => $load->planned_crates,
            'summary' => $this->loads->summary($load),
            'crates' => $crates->getCollection()->map(fn (Crate $c) => [
                'id' => $c->id, 'code' => $c->code, 'variety' => $c->variety?->name, 'size' => $c->size?->name,
                'pallet' => $c->pallet?->code, 'weight' => (float) $c->weight,
            ]),
            'more' => $crates->total() > $crates->count(),
        ]);
    }

    /**
     * Asigna cajones: por ids seleccionados, por códigos escaneados, por pallet o "los primeros N"
     * que cumplen los filtros (FIFO). Devuelve asignados y rechazados (p.ej. tomados por otro usuario).
     */
    public function assign(Request $request, Load $load): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:'.LoadService::MAX_PER_REQUEST],
            'ids.*' => ['integer'],
            'codes' => ['nullable', 'array', 'max:'.LoadService::MAX_PER_REQUEST],
            'codes.*' => ['string', 'max:60'],
            'pallet_code' => ['nullable', 'string', 'max:60'],
            'take' => ['nullable', 'integer', 'min:1', 'max:'.LoadService::MAX_PER_REQUEST],
            'filters' => ['nullable', 'array'],
        ]);

        $rejected = [];
        if (! empty($data['pallet_code'])) {
            $pallet = Pallet::query()->where('code', trim($data['pallet_code']))->orWhere('barcode', trim($data['pallet_code']))->first();
            if (! $pallet) {
                return response()->json(['message' => 'No existe un pallet con ese código.'], 422);
            }
            $result = $this->loads->assignPallet($load, $pallet, $request->user());
        } else {
            $ids = $data['ids'] ?? [];
            if (! empty($data['codes'])) {
                $resolved = $this->loads->resolveCodes($data['codes']);
                $ids = array_merge($ids, $resolved['ids']);
                $rejected = $resolved['rejected'];
            }
            if (! empty($data['take'])) {
                $ids = array_merge($ids, $this->loads->takeAvailable($load, $this->filters(new Request($data['filters'] ?? [])), (int) $data['take']));
            }
            $result = $this->loads->assignCrates($load, $ids, $request->user());
        }

        $result['rejected'] = array_merge($rejected, $result['rejected']);
        $assigned = $result['assigned'];
        $message = $assigned > 0 ? "Se agregaron {$assigned} cajón/es a la carga." : 'No se agregó ningún cajón.';
        if ($result['rejected']) {
            $message .= ' '.count($result['rejected']).' no se pudieron agregar.';
        }

        return response()->json(['message' => $message] + $result);
    }

    public function remove(Request $request, Load $load): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.LoadService::MAX_PER_REQUEST],
            'ids.*' => ['integer'],
        ]);
        $result = $this->loads->removeCrates($load, $data['ids'], $request->user());

        return response()->json(['message' => "Se quitaron {$result['removed']} cajón/es."] + $result);
    }

    public function closeSummary(Load $load): View|RedirectResponse
    {
        if ($load->status !== LoadStatus::Draft) {
            return redirect()->route('loads.show', $load)->with('error', 'La carga no está en armado.');
        }

        return view('loads.close', [
            'load' => $load->load(['truck', 'driver', 'destination', 'client', 'owner']),
            'summary' => $this->loads->summary($load),
        ]);
    }

    public function close(Request $request, Load $load): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['accepted'],
            'version' => ['required', 'integer'],
        ], ['confirm.accepted' => 'Confirmá que revisaste el resumen antes de cerrar la carga.']);

        $this->loads->close($load, $request->user(), (int) $data['version']);

        return redirect()->route('loads.show', $load)->with('success', "Carga {$load->number} cerrada.");
    }

    public function reopen(Request $request, Load $load): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->loads->reopen($load, $data['reason'], $request->user());

        return redirect()->route('loads.builder', $load)->with('success', 'Carga reabierta. Los cambios quedan auditados.');
    }

    public function cancel(Request $request, Load $load): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->loads->cancel($load, $data['reason'], $request->user());

        return redirect()->route('loads.show', $load)->with('success', 'Carga cancelada y cajones liberados.');
    }

    /** Checklist de despacho. */
    public function dispatchForm(Load $load): View
    {
        $load->load(['truck', 'driver', 'destination', 'client', 'dispatchChecks.user']);

        return view('loads.dispatch', [
            'load' => $load,
            'items' => DispatchCheck::ITEMS,
            'checks' => $load->dispatchChecks->keyBy('item'),
            'pending' => $this->loads->pendingChecks($load),
            'remito' => $load->activeRemito()->first(),
        ]);
    }

    public function check(Request $request, Load $load): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'in:'.implode(',', array_keys(DispatchCheck::ITEMS))],
            'checked' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $this->loads->checkItem($load, $data['item'], (bool) $data['checked'], $request->user(), $data['notes'] ?? null);

        // Vuelve directo a la lista de controles (en tablet no hay que desplazarse de nuevo).
        return redirect()->to(route('loads.dispatch.show', $load).'#controles')
            ->with('success', DispatchCheck::ITEMS[$data['item']].($data['checked'] ? ': controlado.' : ': desmarcado.'));
    }

    public function dispatch(Request $request, Load $load): RedirectResponse
    {
        $this->loads->dispatch($load, $request->user());

        return redirect()->route('loads.show', $load)->with('success', "Carga {$load->number} despachada.");
    }

    private function filters(Request $request): array
    {
        // Sólo valores escalares y con el formato esperado: un filtro manipulado (array, fecha inválida)
        // se ignora en vez de provocar un error.
        $filters = array_filter($request->only(['variety_id', 'size_id', 'lot_id', 'producer_id', 'owner_id', 'pallet_id', 'weight_min', 'weight_max',
            'date_from', 'date_to', 'q', 'status']), fn ($v) => is_scalar($v) && $v !== '');

        foreach (['variety_id', 'size_id', 'lot_id', 'producer_id', 'owner_id', 'pallet_id'] as $key) {
            if (isset($filters[$key]) && ! ctype_digit((string) $filters[$key])) {
                unset($filters[$key]);
            }
        }
        foreach (['weight_min', 'weight_max'] as $key) {
            if (isset($filters[$key]) && ! is_numeric($filters[$key])) {
                unset($filters[$key]);
            }
        }
        foreach (['date_from', 'date_to'] as $key) {
            if (isset($filters[$key]) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters[$key])) {
                unset($filters[$key]);
            }
        }
        if (isset($filters['q'])) {
            $filters['q'] = addcslashes(mb_substr((string) $filters['q'], 0, 60), '%_\\');
        }

        return $filters;
    }

    private function options(): array
    {
        return [
            'trucks' => Truck::query()->where('active', true)->orderBy('plate')->get()->mapWithKeys(fn ($t) => [$t->id => $t->plate.' — '.trim($t->brand.' '.$t->model)]),
            'drivers' => Driver::query()->where('active', true)->orderBy('last_name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->full_name.($d->licenseExpired() ? ' (licencia vencida)' : '')]),
            'transporters' => Transporter::query()->where('active', true)->orderBy('business_name')->pluck('business_name', 'id'),
            'destinations' => Destination::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
            'destinationClients' => Destination::query()->where('active', true)->pluck('client_id', 'id'),
            'clients' => Client::query()->where('active', true)->orderBy('business_name')->pluck('business_name', 'id'),
            'owners' => Owner::query()->where('active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
