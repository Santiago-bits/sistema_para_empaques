<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Developer\LicenseController;
use App\Models\ClientTicket;
use App\Models\License;
use App\Models\UsageReport;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Panel General: alta de empaques clientes, su licencia y cuánto usan el sistema. */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys(LicenseController::STATUSES))]]);

        $clients = License::query()
            ->with('latestReport')
            ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed'])])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('client_name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('installation_id', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('locality', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('client_name')
            ->paginate($this->perPage($request))->withQueryString();

        $all = License::query()->with('latestReport')->get();

        return view('central.clients.index', [
            'clients' => $clients,
            'stats' => [
                'clients' => $all->count(),
                'online' => $all->filter->isOnline()->count(),
                'crates_30d' => $all->sum(fn (License $l) => (int) ($l->latestReport?->metric('crates_30d') ?? 0)),
                'users_active' => $all->sum(fn (License $l) => (int) ($l->latestReport?->metric('users_active_7d') ?? 0)),
                'open_tickets' => ClientTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
                'expiring' => $all->filter(fn (License $l) => $l->expires_on && $l->expires_on->between(today(), today()->addDays(30)))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('central.clients.form', ['license' => new License(['plan' => 'standard', 'status' => 'active', 'starts_on' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['installation_id'] = ($data['installation_id'] ?? null) ?: Str::slug(Str::limit($data['client_name'], 40, ''), '-').'-'.Str::lower(Str::random(6));
        $license = License::query()->create($data + [
            'license_key' => strtoupper(implode('-', str_split(Str::random(24), 6))),
        ]);

        return redirect()->route('central.clients.show', $license)
            ->with('success', 'Cliente creado. Copiá los datos de conexión en el .env de su servidor.');
    }

    public function show(License $license): View
    {
        $reports = UsageReport::query()->where('license_id', $license->id)->where('reported_at', '>=', now()->subDays(30))
            ->orderBy('reported_at')->get();
        // Un punto por día (el último reporte de cada día).
        $daily = $reports->groupBy(fn (UsageReport $r) => $r->reported_at->toDateString())->map->last();

        return view('central.clients.show', [
            'license' => $license->load('latestReport'),
            'daily' => $daily,
            'tickets' => ClientTicket::query()->where('license_id', $license->id)->latest('last_message_at')->limit(20)->get(),
            'centralUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function edit(License $license): View
    {
        return view('central.clients.form', ['license' => $license]);
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $data = $this->validated($request, $license);
        if (blank($data['installation_id'] ?? null)) {
            unset($data['installation_id']);
        }
        $license->update($data);

        return redirect()->route('central.clients.show', $license)->with('success', 'Cliente actualizado. El empaque lo verá en su próxima sincronización.');
    }

    private function validated(Request $request, ?License $license = null): array
    {
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'installation_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('licenses')->ignore($license?->id)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'locality' => ['nullable', 'string', 'max:120'],
            'plan' => ['required', Rule::in(array_keys(LicenseController::PLANS))],
            'status' => ['required', Rule::in(array_keys(LicenseController::STATUSES))],
            'starts_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::in(array_keys(ModuleService::CATALOG))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['client_name' => 'empaque', 'installation_id' => 'ID de instalación', 'contact_name' => 'contacto', 'contact_phone' => 'teléfono',
            'contact_email' => 'email', 'locality' => 'localidad', 'starts_on' => 'inicio', 'expires_on' => 'vencimiento']);
        $data['modules'] = array_values($data['modules'] ?? []);

        return $data;
    }
}
