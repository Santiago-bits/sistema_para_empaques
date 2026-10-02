<?php

namespace App\Http\Controllers\Catalogs;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\CatalogRequest;
use App\Models\AuditLog;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador único para todos los catálogos. El comportamiento de cada uno
 * (campos, columnas, filtros, reglas) lo define su CatalogDefinition; el
 * parámetro `catalog` llega como default de la ruta (ver routes/modules/catalogs.php).
 */
class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalogs)
    {
    }

    /** Página principal de catálogos agrupada por área. */
    public function hub(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('catalogs.view') || $user->can('packers.view') || $user->can('lots.view') || $user->can('staff.view'), 403);

        $groups = collect(CatalogRegistry::all())
            ->filter(fn (CatalogDefinition $d) => $user->can($d->viewPermission()))
            ->groupBy(fn (CatalogDefinition $d) => $d->group());

        return view('catalogs.hub', ['groups' => $groups]);
    }

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $definition = $this->definition($request);

        $query = $definition->query();
        if ($request->filled('q')) {
            $definition->applySearch($query, trim((string) $request->query('q')));
        }
        foreach ($definition->filters() as $filter) {
            $value = $request->query($filter->name);
            if (is_string($value) && $value !== '' && array_key_exists($value, $filter->options())) {
                $filter->apply($query, $value);
            }
        }
        $definition->applyOrder($query);

        if (in_array($request->query('format'), ['xlsx', 'csv'], true) && $definition->importColumns() !== []) {
            return $this->export($request, $definition, $query, (string) $request->query('format'));
        }

        return view('catalogs.index', [
            'definition' => $definition,
            'records' => $query->paginate($this->perPage($request))->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $definition = $this->definition($request);
        $this->authorize($definition->managePermission());

        return view('catalogs.form', ['definition' => $definition, 'record' => $definition->newRecord()]);
    }

    public function store(CatalogRequest $request): RedirectResponse
    {
        $definition = $request->definition();
        $record = $this->catalogs->save($definition, $definition->newRecord(), $request->validated());

        return $this->afterSave($definition, $record->getKey(), $definition->savedMessage(true));
    }

    public function show(Request $request): View
    {
        $definition = $this->definition($request);
        abort_unless($definition->hasShow(), 404);
        $model = $definition->find($request->route('record'));

        return view($definition->showView(), array_merge([
            'definition' => $definition,
            'record' => $model,
            'history' => $request->user()->can('audit.view')
                ? AuditLog::query()->with('user:id,first_name,last_name')
                    ->where('auditable_type', $model->getMorphClass())->where('auditable_id', $model->getKey())
                    ->latest('created_at')->latest('id')->limit(15)->get()
                : collect(),
        ], $definition->showData($model)));
    }

    public function edit(Request $request): View
    {
        $definition = $this->definition($request);
        $this->authorize($definition->managePermission());

        return view('catalogs.form', ['definition' => $definition, 'record' => $definition->find($request->route('record'))]);
    }

    public function update(CatalogRequest $request): RedirectResponse
    {
        $definition = $request->definition();
        $model = $this->catalogs->save($definition, $request->record(), $request->validated());

        return $this->afterSave($definition, $model->getKey(), $definition->savedMessage(false));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $definition = $this->definition($request);
        $this->authorize($definition->managePermission());
        $message = $this->catalogs->toggle($definition, $definition->find($request->route('record')));

        return back()->with('success', $message);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $definition = $this->definition($request);
        $this->authorize($definition->managePermission());
        $this->catalogs->delete($definition, $definition->find($request->route('record')));

        return redirect()->to($definition->route('index'))->with('success', 'Registro eliminado.');
    }

    /**
     * Exporta el catálogo (respetando búsqueda y filtros) con los MISMOS encabezados que la importación:
     * se puede abrir en Excel, corregir y volver a importar con «actualizar los existentes».
     */
    private function export(Request $request, CatalogDefinition $definition, $query, string $format): \Symfony\Component\HttpFoundation\Response
    {
        $columns = $definition->importColumns();
        if ($definition->hasActive() && ! in_array('active', $columns, true)) {
            $columns['activo'] = 'active';
        }
        $spec = [];
        foreach ($columns as $header => $field) {
            $spec[$header] = ['label' => $header, 'type' => 'text'];
        }

        $dataset = new \App\Services\Reports\ReportDataset($definition->key(), $definition->title(), $spec,
            fn () => (clone $query)->lazyById(500)->map(function ($record) use ($definition, $columns) {
                $row = [];
                foreach ($columns as $header => $field) {
                    $value = $definition->exportValue($record, $field);
                    $row[$header] = $value === null ? null : (string) $value;
                }

                return $row;
            }));

        return app(\App\Services\ExportService::class)->download($dataset, $format,
            \App\Services\Reports\ReportFilters::between(today(), today()), $request->user());
    }

    /** La clave del catálogo llega como default de la ruta (no por posición). */
    private function definition(Request $request): CatalogDefinition
    {
        return CatalogRegistry::get((string) $request->route('catalog'));
    }

    private function afterSave(CatalogDefinition $definition, mixed $id, string $message): RedirectResponse
    {
        $target = $definition->hasShow() ? $definition->route('show', $id) : $definition->route('index');

        return redirect()->to($target)->with('success', $message);
    }
}
