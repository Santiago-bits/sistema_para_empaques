<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'mode' => ['nullable', Rule::in(array_keys(\App\Services\ImportService::MODES))],
        ], [], ['type' => 'tipo', 'file' => 'archivo', 'mode' => 'modo']);

        $batch = $this->imports->upload($data['type'], $request->file('file'), $request->user(), $data['mode'] ?? 'create');

        return redirect()->route('imports.show', $batch)->with('info', 'Archivo validado. Revisá el resultado antes de confirmar.');
    }

    public function show(ImportBatch $import): View
    {
        return view('imports.show', [
            'batch' => $import->load('user'),
            'typeLabel' => $this->imports->types()[$import->type] ?? null,
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

    /** Subir una carpeta entera (o varias planillas juntas). */
    public function folderCreate(): View
    {
        return view('imports.folder-create', ['group' => (string) Str::uuid()]);
    }

    /** Una planilla de la carpeta (el navegador las manda de a una, así no hay límite de cantidad). */
    public function folderUpload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'uuid'],
            'file' => ['required', 'file', 'max:51200', 'mimes:csv,txt,xlsx,zip'],
            'mode' => ['nullable', Rule::in(array_keys(ImportService::MODES))],
        ], [], ['file' => 'archivo', 'mode' => 'modo']);
        $foreign = ImportBatch::query()->where('group_uuid', $data['group'])->where('user_id', '!=', $request->user()->id)->exists();
        abort_if($foreign, 403);

        $file = $request->file('file');
        $mode = $data['mode'] ?? 'upsert';
        if (strtolower($file->getClientOriginalExtension()) === 'zip') {
            $batches = $this->imports->uploadZip($file, $request->user(), $mode, $data['group']);
        } else {
            abort_if($file->getSize() > ImportService::ZIP_MAX_FILE_BYTES, 422, 'La planilla supera los 10 MB.');
            $batches = [$this->imports->uploadDetected($file, $request->user(), $mode, $data['group'])];
        }

        return response()->json(['files' => array_map(fn (ImportBatch $batch) => [
            'filename' => $batch->filename,
            'recognized' => $batch->status === 'validated',
            'section' => $this->imports->types()[$batch->type] ?? null,
            'reason' => $batch->detection['reason'] ?? null,
            'valid_rows' => $batch->valid_rows,
            'error_rows' => $batch->error_rows,
        ], $batches)]);
    }

    public function folderShow(string $group): View
    {
        // Números realistas para las planillas que dependen de otras de la misma carpeta (no guarda nada).
        $this->imports->simulateGroup($group);
        $batches = ImportBatch::query()->where('group_uuid', $group)->with('user:id,first_name,last_name')->orderBy('id')->get();
        abort_if($batches->isEmpty(), 404);

        return view('imports.folder-show', ['group' => $group, 'batches' => $batches, 'types' => $this->imports->types()]);
    }

    public function folderConfirm(string $group): RedirectResponse
    {
        $result = $this->imports->confirmGroup($group);
        $total = array_sum($result['imported']);
        $message = 'Se importaron '.num($total).' registro(s) de '.count($result['imported']).' planilla(s).';
        if ($result['failed'] !== []) {
            return redirect()->route('imports.folder.show', $group)->with('warning', $message.' No se pudieron importar: '
                .collect($result['failed'])->map(fn ($error, $file) => $file.' ('.$error.')')->implode('; '));
        }

        return redirect()->route('imports.folder.show', $group)->with('success', $message);
    }

    public function folderDiscard(string $group): RedirectResponse
    {
        $count = $this->imports->discardGroup($group);

        return redirect()->route('imports.index')->with('success', "Se descartaron {$count} planilla(s). No se guardó ningún dato.");
    }

    /** Elegir (o corregir) a mano la sección de una planilla. */
    public function retype(Request $request, ImportBatch $import): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys($this->imports->types()))]], [], ['type' => 'sección']);
        $this->imports->retype($import, $data['type'], by: $request->user());
        $back = $import->group_uuid ? route('imports.folder.show', $import->group_uuid) : route('imports.show', $import);

        return redirect($back)->with('success', '«'.$import->filename.'» va a '.mb_strtolower($this->imports->types()[$data['type']]).'. Revisá el resultado antes de confirmar.');
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
