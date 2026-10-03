<?php

namespace App\Http\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «Importar y exportar»: un solo lugar con todo lo que se puede bajar a Excel o subir desde Excel
 * (clientes, proveedores, camioneros, camiones, embaladores, etc.). Cada usuario ve sólo lo que tiene permiso.
 */
class TransferController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canImport = $user->can('imports.manage');

        $catalogs = collect(CatalogRegistry::importable())
            ->filter(fn (CatalogDefinition $d) => module_enabled('catalogs') && $user->can($d->viewPermission()))
            ->map(fn (CatalogDefinition $d) => [
                'title' => $d->title(),
                'description' => $d->description(),
                'icon' => $d->icon(),
                'count' => $d->count(),
                'excel' => $d->route('index').'?format=xlsx',
                'list' => $d->route('index'),
                'import' => $canImport ? route('imports.create', ['type' => $d->key()]) : null,
                'template' => $canImport ? route('imports.template', $d->key()) : null,
            ])->values();

        $from = today()->subDays(30)->toDateString();
        $to = today()->toDateString();
        $movements = collect();
        if (module_enabled('reports') && $user->can('reports.view') && $user->can('reports.export_excel')) {
            foreach ([
                ['production', 'detail', 'Producción (cajón por cajón)', 'box'],
                ['loads', 'summary', 'Cargas y despachos', 'truck'],
                ['waste', 'detail', 'Rechazos y merma', 'trash'],
                ['packers', 'summary', 'Producción por embalador', 'users'],
            ] as [$report, $variant, $label, $icon]) {
                $movements->push([
                    'title' => $label, 'icon' => $icon,
                    'excel' => route('reports.show', $report).'?'.http_build_query(['from' => $from, 'to' => $to, 'format' => 'xlsx', 'variant' => $variant]),
                    'page' => route('reports.show', $report),
                ]);
            }
        }
        if (module_enabled('treasury') && $user->can('treasury.view')) {
            $movements->push(['title' => 'Cuentas corrientes (saldos)', 'icon' => 'users', 'excel' => route('accounts.index').'?format=xlsx', 'page' => route('accounts.index')]);
            $movements->push(['title' => 'Cheques', 'icon' => 'receipt', 'excel' => route('checks.index').'?format=xlsx', 'page' => route('checks.index')]);
        }

        abort_if($catalogs->isEmpty() && $movements->isEmpty(), 403);

        return view('transfer.index', compact('catalogs', 'movements', 'canImport'));
    }
}
