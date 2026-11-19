<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Cuit;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Support\Legal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Datos del titular para los términos y condiciones, y quién los aceptó en cada galpón. */
class LegalSettingsController extends Controller
{
    private const FIELDS = ['provider_name', 'provider_cuit', 'provider_address', 'email', 'jurisdiction'];

    public function edit(): View
    {
        return view('superadmin.legal', [
            'values' => collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => (string) setting('legal.'.$f, '')])->all(),
            'version' => Legal::VERSION,
            // Administradores de cada galpón y si ya aceptaron la versión vigente.
            'admins' => User::query()->whereNotNull('warehouse_id')->whereHas('role', fn ($q) => $q->where('slug', \App\Models\Role::ADMIN))
                ->with('warehouse:id,name')->orderBy('warehouse_id')->get(['id', 'first_name', 'last_name', 'username', 'warehouse_id', 'terms_version', 'terms_accepted_at']),
        ]);
    }

    public function update(Request $request, SettingsService $settings, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:120'],
            'provider_cuit' => ['nullable', 'string', 'max:13', new Cuit],
            'provider_address' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:120'],
            'jurisdiction' => ['nullable', 'string', 'max:200'],
        ], [], ['provider_name' => 'nombre del titular', 'provider_cuit' => 'CUIT', 'provider_address' => 'domicilio', 'jurisdiction' => 'jurisdicción']);

        foreach (self::FIELDS as $field) {
            $settings->set('legal.'.$field, trim((string) ($data[$field] ?? '')));
        }
        $audit->log('settings', null, null, $data, 'Actualizó los datos del titular de los términos y condiciones');

        return redirect()->route('superadmin.legal')->with('success', 'Datos guardados. Ya se ven en los términos y en la política de privacidad.');
    }
}
