<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Incident::STATUSES), 'active'])],
            'type' => ['nullable', Rule::in(array_keys(Incident::TYPES))],
            'priority' => ['nullable', Rule::in(array_keys(Incident::PRIORITIES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $request->query('status', 'active');

        return view('incidents.index', [
            'incidents' => Incident::query()->with(['reporter:id,first_name,last_name', 'responsible:id,first_name,last_name'])
                ->when($status === 'active', fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
                ->when($status !== 'active', fn ($q) => $q->where('status', $status))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
                ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%'.addcslashes((string) $request->query('q'), '%_\\').'%';
                    $q->where(fn ($w) => $w->where('number', 'like', $term)->orWhere('description', 'like', $term)->orWhere('area', 'like', $term));
                })
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->latest('occurred_at')->paginate($this->perPage($request))->withQueryString(),
            'status' => $status,
            'counts' => Incident::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('incidents.form', ['incident' => new Incident(['occurred_at' => now(), 'priority' => 'medium', 'type' => 'other']), 'users' => $this->users()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $incident = $this->incidents->create($this->validated($request), $request->user());

        return redirect()->route('incidents.show', $incident)->with('success', 'Incidente '.$incident->number.' registrado.');
    }

    public function show(Incident $incident): View
    {
        return view('incidents.show', [
            'incident' => $incident->load('reporter', 'responsible', 'related', 'stateHistories.user'),
            'transitions' => IncidentService::TRANSITIONS[$incident->status] ?? [],
        ]);
    }

    public function edit(Incident $incident): View
    {
        return view('incidents.form', ['incident' => $incident->load('related'), 'users' => $this->users()]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $this->incidents->update($incident, $this->validated($request));

        return redirect()->route('incidents.show', $incident)->with('success', 'Incidente actualizado.');
    }

    public function status(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Incident::STATUSES))],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ], [], ['resolution' => 'resolución']);
        $this->incidents->changeStatus($incident, $data['status'], $request->user(), $data['resolution'] ?? null);

        return back()->with('success', 'Estado actualizado: '.Incident::STATUSES[$data['status']].'.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(Incident::TYPES))],
            'occurred_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString()],
            'area' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Incident::PRIORITIES))],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', UserStatus::Active->value)],
            'related_type' => ['nullable', Rule::in(array_keys(IncidentService::RELATED))],
            'related_code' => ['nullable', 'required_with:related_type', 'string', 'max:60'],
        ], [], ['type' => 'tipo', 'occurred_at' => 'fecha y hora', 'description' => 'descripción', 'priority' => 'prioridad',
            'responsible_id' => 'responsable', 'related_type' => 'tipo de registro', 'related_code' => 'código']);
    }

    /** @return array<int, string> */
    private function users(): array
    {
        return User::query()->visibleTo()->where('status', UserStatus::Active->value)->orderBy('last_name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])->all();
    }
}
