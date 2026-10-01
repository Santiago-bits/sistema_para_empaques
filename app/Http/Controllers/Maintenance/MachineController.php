<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\MachineRequest;
use App\Http\Requests\Maintenance\MaintenanceRequest;
use App\Models\Machine;
use App\Models\Maintenance;
use App\Models\WarehouseLocation;
use App\Services\MaintenanceService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    public function index(Request $request): View
    {
        $days = MaintenanceService::UPCOMING_DAYS;

        $machines = Machine::query()->with('location:id,name')
            ->withMax('maintenances', 'date')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('name', 'like', $term)
                    ->orWhere('brand', 'like', $term)->orWhere('serial_number', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->input('due') === 'overdue', fn ($q) => $q->whereDate('next_maintenance_on', '<', today()))
            ->when($request->input('due') === 'upcoming', fn ($q) => $q->whereDate('next_maintenance_on', '>=', today())
                ->whereDate('next_maintenance_on', '<=', today()->addDays($days)))
            // Primero los vencidos y próximos; los que no tienen fecha al final.
            ->orderByRaw('CASE WHEN next_maintenance_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_maintenance_on')->orderBy('code')
            ->paginate($this->perPage($request))->withQueryString();

        return view('machines.index', [
            'machines' => $machines,
            'statuses' => Machine::STATUSES,
            'summary' => $this->maintenance->summary(),
            'days' => $days,
        ]);
    }

    public function create(): View
    {
        return view('machines.form', $this->formData(new Machine(['status' => 'operational'])));
    }

    public function store(MachineRequest $request): RedirectResponse
    {
        $machine = Machine::query()->create($request->validated());

        return redirect()->route('machines.show', $machine)->with('success', 'Máquina creada.');
    }

    public function show(Request $request, Machine $machine): View
    {
        $maintenances = $machine->maintenances()->with('user:id,first_name,last_name')
            ->latest('date')->latest('id')->paginate($this->perPage($request))->withQueryString();

        return view('machines.show', [
            'machine' => $machine->load('location'),
            'maintenances' => $maintenances,
            'totalCost' => (float) $machine->maintenances()->sum('cost'),
            'due' => MaintenanceService::dueStatus($machine),
            'types' => Maintenance::TYPES,
            'statuses' => Machine::STATUSES,
        ]);
    }

    public function edit(Machine $machine): View
    {
        return view('machines.form', $this->formData($machine));
    }

    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $machine->update($request->validated());

        return redirect()->route('machines.show', $machine)->with('success', 'Máquina actualizada.');
    }

    public function storeMaintenance(MaintenanceRequest $request, Machine $machine): RedirectResponse
    {
        $this->maintenance->register($machine, $request->validated());

        return redirect()->route('machines.show', $machine)->with('success', 'Mantenimiento registrado.');
    }

    private function formData(Machine $machine): array
    {
        return [
            'machine' => $machine,
            'statuses' => Machine::STATUSES,
            'locations' => WarehouseLocation::query()->where('warehouse_id', CurrentWarehouse::id())->orderBy('code')->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->code.' — '.$l->name]),
        ];
    }
}
