<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel de módulos. Desactivar un módulo nunca borra datos: sólo oculta el
 * menú, bloquea las URLs y sus permisos. Puede reactivarse en cualquier momento.
 */
class ModuleController extends Controller
{
    public function index(): View
    {
        return view('admin.modules.index', ['modules' => Module::query()->whereNotIn('key', \App\Services\ModuleService::REMOVED)->orderBy('sort')->get()]);
    }

    public function update(Request $request, Module $module, ModuleService $modules): RedirectResponse
    {
        abort_if(in_array($module->key, \App\Services\ModuleService::REMOVED, true), 404);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        if ($module->is_core) {
            return back()->with('error', 'Los módulos núcleo no se pueden desactivar.');
        }

        $modules->setEnabled($module->key, (bool) $data['enabled']);

        return back()->with('success', "Módulo «{$module->name}» ".($data['enabled'] ? 'activado' : 'desactivado').'.');
    }
}
