<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos adjuntos polimórficos. Los archivos se guardan en el disco privado
 * (storage/app/private/documents/AAAA/MM) con nombre aleatorio y sólo se sirven
 * mediante una ruta autenticada que verifica permisos.
 */
class DocumentService
{
    public const DISK = 'local';

    public const MAX_KB = 10240;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'docx'];

    /** Entidades que admiten adjuntos: alias morph => [etiqueta, permiso para ver la entidad]. */
    public const ENTITIES = [
        'load' => ['Carga', 'loads.view'],
        'pallet' => ['Pallet', 'pallets.view'],
        'client' => ['Cliente', 'catalogs.view'],
        'invoice' => ['Comprobante', 'billing.view'],
        'remito' => ['Remito', 'remitos.view'],
        'lot' => ['Lote', 'lots.view'],
        'transporter' => ['Transportista', 'catalogs.view'],
    ];

    public function __construct(private readonly AuditService $audit)
    {
    }

    /** Resuelve la entidad destino (sólo los tipos permitidos). */
    public function resolve(string $type, int $id): Model
    {
        if (! array_key_exists($type, self::ENTITIES)) {
            throw new BusinessException('Tipo de entidad no admitido para adjuntos.');
        }
        $class = Relation::getMorphedModel($type);
        $model = $class ? $class::query()->find($id) : null;
        if (! $model) {
            throw new BusinessException('La entidad indicada no existe.');
        }

        return $model;
    }

    public function store(Model $documentable, UploadedFile $file, string $type, ?string $title, User $by): Document
    {
        if (! array_key_exists($type, Document::TYPES)) {
            throw new BusinessException('Tipo de documento inválido.');
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        $dir = 'documents/'.now()->format('Y/m');
        $name = Str::random(40).'.'.$extension;
        $path = $file->storeAs($dir, $name, self::DISK);
        if (! $path) {
            throw new BusinessException('No se pudo guardar el archivo. Intentá nuevamente.');
        }

        try {
            return DB::transaction(function () use ($documentable, $file, $type, $title, $by, $path) {
                $original = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 250, '');

                $document = Document::query()->create([
                    'documentable_type' => $documentable->getMorphClass(),
                    'documentable_id' => $documentable->getKey(),
                    'type' => $type,
                    'title' => $title ?: pathinfo($original, PATHINFO_FILENAME),
                    'path' => $path,
                    'original_name' => $original,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $by->id,
                ]);

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }

    /** ¿Puede el usuario ver este documento? Requiere documents.view + permiso de la entidad. */
    public function canView(User $user, Document $document): bool
    {
        $entityPermission = self::ENTITIES[$document->documentable_type][1] ?? null;

        return $user->can('documents.view') && ($entityPermission === null || $user->can($entityPermission));
    }

    public function download(Document $document, bool $inline = false): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($document->path)) {
            throw new BusinessException('El archivo no se encuentra en el servidor.');
        }

        $headers = [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        // Sólo PDF e imágenes se muestran en línea; el resto siempre se descarga.
        $canInline = $inline && in_array($document->mime, ['application/pdf', 'image/png', 'image/jpeg'], true);

        return $canInline
            ? $disk->response($document->path, $document->original_name, $headers)
            : $disk->download($document->path, $document->original_name, $headers);
    }

    /** Eliminación lógica: el archivo se conserva para auditoría. */
    public function delete(Document $document, User $by): void
    {
        $document->delete();
    }
}
