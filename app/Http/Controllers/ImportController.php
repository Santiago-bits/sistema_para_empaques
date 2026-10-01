<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importación validada: subir → revisar resumen/vista previa/errores → confirmar.
 * Nunca se importa nada sin la confirmación explícita del usuario.
 */
class ImportController extends Controller
{
    public function __construct(private readonly ImportService $imports)
    {
    }

    public function index(Request $request): View
    {
        return view('imports.index', [
            'batches' => ImportBatch::query()->with('user:id,first_name,last_name')->latest('id')
                ->paginate($this->perPage($request))->withQueryString(),
            'types' => $this->imports->types(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('imports.create', ['types' => $this->imports->types(), 'type' => $request->query('type')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($this->imports->types()))],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
        ], [], ['type' => 'tipo', 'file' => 'archivo']);

        $batch = $this->imports->upload($data['type'], $request->file('file'), $request->user());

        return redirect()->route('imports.show', $batch)->with('info', 'Archivo validado. Revisá el resultado antes de confirmar.');
    }

    public function show(ImportBatch $import): View
    {
        return view('imports.show', [
            'batch' => $import->load('user'),
            'typeLabel' => $this->imports->types()[$import->type] ?? $import->type,
            'preview' => $import->status === 'validated' ? $this->imports->preview($import) : ['headers' => [], 'rows' => []],
        ]);
    }

    public function confirm(ImportBatch $import): RedirectResponse
    {
        $count = $this->imports->confirm($import);

        return redirect()->route('imports.show', $import)->with('success', "Se importaron {$count} registro(s).");
    }

    public function discard(ImportBatch $import): RedirectResponse
    {
        $this->imports->discard($import);

        return redirect()->route('imports.index')->with('success', 'Importación descartada. No se guardó ningún dato.');
    }

    /** Descarga los errores de validación como CSV (para corregir la planilla). */
    public function errors(ImportBatch $import): StreamedResponse
    {
        $errors = $import->errors ?? [];

        return response()->streamDownload(function () use ($errors) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['fila', 'campo', 'error'], ';');
            foreach ($errors as $error) {
                fputcsv($out, [$error['row'] ?? '', $error['field'] ?? '', $error['message'] ?? ''], ';');
            }
            fclose($out);
        }, 'errores_importacion_'.$import->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Plantilla vacía con los encabezados esperados. */
    public function template(string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, $this->imports->types()), 404);
        $headers = $this->imports->templateHeaders($type);

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            fclose($out);
        }, 'plantilla_'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
