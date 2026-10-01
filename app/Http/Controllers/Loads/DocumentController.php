<?php

namespace App\Http\Controllers\Loads;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Documentación adjunta (remitos firmados, certificados, transporte...) asociada a entidades. */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        // Sólo tipos de entidad que el usuario puede ver.
        $visibleTypes = collect(DocumentService::ENTITIES)->filter(fn ($e) => $user->can($e[1]))->keys()->all();

        $documents = Document::query()
            ->with(['documentable', 'uploader:id,first_name,last_name'])
            ->whereIn('documentable_type', $visibleTypes)
            ->when($request->filled('type') && array_key_exists($request->query('type'), Document::TYPES), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('entity') && in_array($request->query('entity'), $visibleTypes, true), fn ($q) => $q->where('documentable_type', $request->query('entity')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$request->query('q').'%')
                ->orWhere('original_name', 'like', '%'.$request->query('q').'%')))
            ->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'types' => Document::TYPES,
            'entities' => collect(DocumentService::ENTITIES)->only($visibleTypes)->map(fn ($e) => $e[0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'documentable_type' => ['required', Rule::in(array_keys(DocumentService::ENTITIES))],
            'documentable_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(Document::TYPES))],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.DocumentService::MAX_KB, 'mimes:'.implode(',', DocumentService::MIMES)],
        ], [], ['file' => 'archivo', 'type' => 'tipo', 'title' => 'título']);

        abort_unless($request->user()->can(DocumentService::ENTITIES[$data['documentable_type']][1]), 403);
        $entity = $this->documents->resolve($data['documentable_type'], (int) $data['documentable_id']);
        $this->documents->store($entity, $request->file('file'), $data['type'], $data['title'] ?? null, $request->user());

        return back()->with('success', 'Documento adjuntado.');
    }

    public function download(Request $request, Document $document, AuditService $audit): StreamedResponse
    {
        abort_unless($this->documents->canView($request->user(), $document), 403);
        $audit->log('download', $document, description: 'Descargó el documento '.$document->original_name);

        return $this->documents->download($document, $request->boolean('inline'));
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        abort_unless($this->documents->canView($request->user(), $document), 403);
        $this->documents->delete($document, $request->user());

        return back()->with('success', 'Documento eliminado (se conserva en auditoría).');
    }
}
