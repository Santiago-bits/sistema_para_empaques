<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Licencias por instalación. Una licencia vencida o suspendida NUNCA bloquea datos ni
 * operaciones: sólo muestra un aviso en la barra superior.
 */
class LicenseController extends Controller
{
    public const PLANS = ['standard' => 'Estándar', 'pro' => 'Profesional', 'enterprise' => 'Empresa'];

    public const STATUSES = ['active' => 'Activa', 'suspended' => 'Suspendida', 'expired' => 'Vencida'];

    public function index(): View
    {
        return view('developer.licenses.index', [
            'licenses' => License::query()->orderByDesc('id')->get(),
            'installationId' => config('galpon.installation_id'),
        ]);
    }

    public function create(): View
    {
        return view('developer.licenses.form', ['license' => new License([
            'installation_id' => config('galpon.installation_id'),
            'client_name' => setting('company.name'),
            'plan' => 'standard', 'status' => 'active', 'starts_on' => today(),
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $license = License::query()->create($this->validated($request) + [
            'license_key' => strtoupper(implode('-', str_split(Str::random(24), 6))),
            'version' => config('galpon.version'),
        ]);

        return redirect()->route('developer.licenses.index')->with('success', 'Licencia creada para '.$license->client_name.'.');
    }

    public function edit(License $license): View
    {
        return view('developer.licenses.form', ['license' => $license]);
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $license->update($this->validated($request, $license));

        return redirect()->route('developer.licenses.index')->with('success', 'Licencia actualizada.');
    }

    private function validated(Request $request, ?License $license = null): array
    {
        $data = $request->validate([
            'installation_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('licenses')->ignore($license?->id)],
            'client_name' => ['required', 'string', 'max:255'],
            'plan' => ['required', Rule::in(array_keys(self::PLANS))],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'starts_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::in(array_keys(ModuleService::CATALOG))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['installation_id' => 'ID de instalación', 'client_name' => 'cliente', 'expires_on' => 'vencimiento', 'starts_on' => 'inicio']);

        $data['modules'] = array_values($data['modules'] ?? []);

        return $data;
    }
}
