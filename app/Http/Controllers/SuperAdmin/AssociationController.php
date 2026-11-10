<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Association;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administración general → Asociaciones de citricultores (sólo el Super Administrador).
 * Lista común a todo el sistema; cada galpón se vincula a una o a varias desde su ficha.
 */
class AssociationController extends Controller
{
    public function index(): View
    {
        return view('superadmin.associations.index', [
            'associations' => Association::query()->withCount('warehouses')->with('warehouses:id,name')->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('superadmin.associations.form', ['association' => new Association(['active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Association::query()->create($this->validated($request));

        return redirect()->route('superadmin.associations.index')->with('success', 'Asociación creada. Vinculala a sus galpones desde la ficha de cada galpón.');
    }

    public function edit(Association $association): View
    {
        return view('superadmin.associations.form', ['association' => $association]);
    }

    public function update(Request $request, Association $association): RedirectResponse
    {
        $association->update($this->validated($request, $association));

        return redirect()->route('superadmin.associations.index')->with('success', 'Asociación actualizada.');
    }

    private function validated(Request $request, ?Association $association = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('associations', 'name')->ignore($association?->id)],
            'short_name' => ['nullable', 'string', 'max:60'],
            'cuit' => ['nullable', new \App\Rules\Cuit],
            'locality' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ], [], ['name' => 'nombre', 'short_name' => 'nombre corto', 'locality' => 'localidad', 'province' => 'provincia']);
        $data['cuit'] = filled($data['cuit'] ?? null) ? preg_replace('/\D/', '', (string) $data['cuit']) : null;
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }
}
