<?php

namespace App\Services;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogRegistry;
use App\Exceptions\BusinessException;
use App\Models\ImportBatch;
use App\Models\User;
use DateTimeInterface;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Importación masiva de catálogos desde CSV o XLSX.
 *
 * Flujo: subir → validar TODAS las filas sin escribir nada (ImportBatch "validated")
 * → el usuario revisa resumen, vista previa y errores → confirmar → se vuelve a
 * validar contra la base actual y se insertan sólo las filas válidas, en una
 * transacción y por lotes de 500 → auditoría "import".
 */
class ImportService
{
    public const CHUNK = 500;

    public const MAX_ROWS = 20000;

    public const PREVIEW_ROWS = 10;

    public const MAX_STORED_ERRORS = 2000;

    private const DISK = 'local';

    public function __construct(private readonly AuditService $audit)
    {
    }

    /** @return array<string, string> tipo => etiqueta */
    public function types(): array
    {
        return array_map(fn (CatalogDefinition $d) => $d->title(), CatalogRegistry::importable());
    }

    public function definition(string $type): CatalogDefinition
    {
        $definitions = CatalogRegistry::importable();
        if (! isset($definitions[$type])) {
            throw new BusinessException('Tipo de importación no válido.');
        }

        return $definitions[$type];
    }

    /** @return list<string> */
    public function templateHeaders(string $type): array
    {
        return array_keys($this->definition($type)->importColumns());
    }

    /** Modos: «create» sólo agrega nuevos (los existentes se informan como duplicados); «upsert» además actualiza los existentes. */
    public const MODES = ['create' => 'Sólo agregar nuevos', 'upsert' => 'Agregar nuevos y actualizar los existentes'];

    public function upload(string $type, UploadedFile $file, User $user, string $mode = 'create'): ImportBatch
    {
        $definition = $this->definition($type);
        $mode = array_key_exists($mode, self::MODES) ? $mode : 'create';
        // Sólo extensiones conocidas: nunca se guarda un archivo con la extensión que mande el navegador.
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw new BusinessException('Formato no admitido: subí un archivo CSV o Excel (.xlsx).');
        }
        $path = $file->storeAs('imports', now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension, self::DISK);

        try {
            $result = $this->analyze($definition, $path, $mode);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e instanceof BusinessException ? $e : new BusinessException('No se pudo leer el archivo. Verificá que sea un CSV o XLSX válido.');
        }

        return ImportBatch::query()->create([
            'type' => $type,
            'mode' => $mode,
            'filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'path' => $path,
            'status' => 'validated',
            'total_rows' => $result['total'],
            'valid_rows' => count($result['valid']),
            'updated_rows' => count(array_filter($result['valid'], fn ($r) => isset($r['id']))),
            'error_rows' => $result['error_rows'],
            'errors' => array_slice($result['errors'], 0, self::MAX_STORED_ERRORS),
            'user_id' => $user->id,
        ]);
    }

    /**
     * Importa las filas válidas. Se re-valida contra la base actual por si cambió
     * algo desde la vista previa (otro usuario cargó el mismo código, etc.).
     */
    public function confirm(ImportBatch $batch): int
    {
        return DB::transaction(function () use ($batch) {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'validated') {
                throw new BusinessException('Esta importación ya fue procesada o descartada.');
            }

            $definition = $this->definition($locked->type);
            $result = $this->analyze($definition, $locked->path, $locked->mode ?: 'create');
            $model = $definition->modelClass();
            $now = now();

            $inserts = array_values(array_filter($result['valid'], fn ($r) => ! isset($r['id'])));
            $updates = array_values(array_filter($result['valid'], fn ($r) => isset($r['id'])));

            foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
                $model::query()->insert(array_map(
                    fn (array $row) => $row['data'] + ['created_at' => $now, 'updated_at' => $now],
                    $chunk
                ));
            }
            foreach ($updates as $row) {
                if ($row['data'] !== []) {
                    $model::query()->whereKey($row['id'])->update($row['data'] + ['updated_at' => $now]);
                }
            }

            $imported = count($result['valid']);
            $locked->update([
                'status' => 'imported',
                'imported_at' => $now,
                'total_rows' => $result['total'],
                'valid_rows' => $imported,
                'updated_rows' => count($updates),
                'error_rows' => $result['error_rows'],
                'errors' => array_slice($result['errors'], 0, self::MAX_STORED_ERRORS),
            ]);

            $this->audit->log('import', null, null,
                ['import_batch_id' => $locked->id, 'type' => $locked->type, 'created' => count($inserts), 'updated' => count($updates), 'skipped' => $result['error_rows']],
                'Importó '.count($inserts).' nuevo(s) y actualizó '.count($updates).' registro(s) de '.mb_strtolower($definition->title()).' desde '.$locked->filename);

            return $imported;
        });
    }

    public function discard(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'validated') {
                throw new BusinessException('Sólo se puede descartar una importación pendiente de confirmar.');
            }
            $locked->update(['status' => 'discarded']);
        });
        Storage::disk(self::DISK)->delete($batch->path);
    }

    /**
     * Primeras filas del archivo para la vista previa.
     *
     * @return array{headers: list<string>, rows: list<array{row: int, cells: list<?string>}>}
     */
    public function preview(ImportBatch $batch): array
    {
        if (! Storage::disk(self::DISK)->exists($batch->path)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [];
        $rows = [];
        foreach ($this->readRows(Storage::disk(self::DISK)->path($batch->path)) as $number => $cells) {
            if ($headers === []) {
                $headers = array_map(fn ($h) => (string) $h, $cells);
                continue;
            }
            $rows[] = ['row' => $number, 'cells' => array_pad(array_slice($cells, 0, count($headers)), count($headers), null)];
            if (count($rows) >= self::PREVIEW_ROWS) {
                break;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Lee y valida todas las filas sin escribir nada.
     *
     * @return array{total: int, error_rows: int, valid: list<array{row: int, data: array}>, errors: list<array{row: int, field: string, message: string}>}
     */
    public function analyze(CatalogDefinition $definition, string $path, string $mode = 'create'): array
    {
        $importColumns = $definition->importColumns();
        if ($definition->hasActive() && ! in_array('active', $importColumns, true)) {
            $importColumns['activo'] = 'active';
        }
        $columns = [];
        foreach ($importColumns as $header => $field) {
            $columns[$this->normalizeHeader($header)] = $field;
            $columns[$this->normalizeHeader($field)] = $field;
        }
        $headerByField = array_flip($importColumns);
        $dateFields = collect($definition->fields())->where('type', 'date')->pluck('name')->all();
        $attributes = $definition->attributes();
        $keys = $definition->importKeys();
        $relations = $definition->importRelations();
        $fillable = (new ($definition->modelClass()))->getFillable();
        $upsert = $mode === 'upsert';

        $map = null;
        $present = [];
        $seen = [];
        $total = 0;
        $valid = [];
        $errors = [];
        $errorRows = [];

        foreach ($this->readRows(Storage::disk(self::DISK)->path($path)) as $number => $cells) {
            if ($map === null) {
                $map = [];
                foreach ($cells as $index => $header) {
                    $field = $columns[$this->normalizeHeader((string) $header)] ?? null;
                    if ($field !== null && ! in_array($field, $map, true)) {
                        $map[$index] = $field;
                    }
                }
                if ($map === []) {
                    throw new BusinessException('El archivo no tiene los encabezados esperados. Descargá la plantilla y respetá la primera fila: '
                        .implode(', ', array_keys($definition->importColumns())).'.');
                }
                // Campos que trae el archivo (en una actualización sólo se tocan ésos).
                $present = array_values($map);
                foreach ($relations as $virtual => $real) {
                    if (in_array($virtual, $present, true)) {
                        $present[] = $real;
                    }
                }
                continue;
            }

            if (++$total > self::MAX_ROWS) {
                throw new BusinessException('El archivo supera el máximo de '.num(self::MAX_ROWS).' filas por importación. Dividilo en partes.');
            }

            $row = array_fill_keys(array_values($importColumns), null);
            foreach ($map as $index => $field) {
                $row[$field] = $this->cellValue($cells[$index] ?? null);
            }
            foreach ($dateFields as $field) {
                if (is_string($row[$field] ?? null) && preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $row[$field], $m)) {
                    $row[$field] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                }
            }
            if (array_key_exists('active', $row)) {
                $flag = mb_strtolower(trim((string) $row['active']));
                $row['active'] = $flag === '' ? null : (in_array($flag, ['1', 'si', 'sí', 's', 'true', 'x', 'activo', 'activa'], true) ? 1 : 0);
            }

            $data = $definition->prepare($definition->prepareImport($row));
            $existing = $upsert ? $definition->findForImport($data) : null;
            $rules = $definition->rules($existing);
            if (array_key_exists('active', $data) && $data['active'] === null) {
                unset($data['active']);
            }
            $rowErrors = [];

            // Al actualizar se validan sólo las columnas que trae el archivo (una planilla puede traer sólo CUIT + teléfono).
            if ($existing) {
                $data = array_intersect_key($data, array_flip($present));
            }
            $validator = Validator::make($data, array_intersect_key($rules, $data), [], $attributes);
            if ($validator->fails()) {
                $failed = $validator->failed();
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $message = isset($failed[$field]['Unique'])
                        ? 'Ya existe en el sistema un registro con ese '.($attributes[$field] ?? $field).($upsert ? ' (y no coincide con el resto de los datos).' : ' (duplicado). Para actualizarlo, importá con «Agregar nuevos y actualizar los existentes».')
                        : $messages[0];
                    $rowErrors[] = ['row' => $number, 'field' => $headerByField[$field] ?? $field, 'message' => $message];
                }
            }

            foreach ($keys as $key) {
                $value = $data[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $normalized = mb_strtoupper((string) $value);
                if (isset($seen[$key][$normalized])) {
                    $rowErrors[] = ['row' => $number, 'field' => $headerByField[$key] ?? $key,
                        'message' => 'Duplicado dentro del archivo: el mismo '.($attributes[$key] ?? $key).' ya está en la fila '.$seen[$key][$normalized].'.'];
                } else {
                    $seen[$key][$normalized] = $number;
                }
            }

            if ($rowErrors !== []) {
                $errorRows[$number] = true;
                array_push($errors, ...$rowErrors);

                continue;
            }

            $clean = array_intersect_key($validator->validated(), array_flip($fillable));
            if ($existing) {
                // Actualización: sólo las columnas que vinieron en el archivo.
                $valid[] = ['row' => $number, 'id' => $existing->getKey(), 'data' => array_intersect_key($clean, array_flip($present))];

                continue;
            }
            if ($definition->hasActive() && ! array_key_exists('active', $clean)) {
                $clean['active'] = true;
            }
            $valid[] = ['row' => $number, 'data' => $clean];
        }

        if ($map === null) {
            throw new BusinessException('El archivo está vacío.');
        }

        return ['total' => $total, 'error_rows' => count($errorRows), 'valid' => $valid, 'errors' => $errors];
    }

    /**
     * Itera las filas no vacías del archivo: número de fila => celdas.
     *
     * @return Generator<int, list<mixed>>
     */
    public function readRows(string $absolutePath): Generator
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
            $reader->open($absolutePath);
            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $number => $row) {
                        $cells = $row->toArray();
                        if ($this->isEmptyRow($cells)) {
                            continue;
                        }
                        yield $number => $cells;
                    }
                    break; // sólo la primera hoja
                }
            } finally {
                $reader->close();
            }

            return;
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new BusinessException('No se pudo abrir el archivo.');
        }
        try {
            $first = (string) fgets($handle);
            $delimiter = $this->detectDelimiter($first);
            rewind($handle);
            $number = 0;
            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $number++;
                if ($number === 1 && isset($cells[0])) {
                    $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                }
                $cells = array_map(function ($value) {
                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                    }

                    return $value;
                }, $cells);
                if ($this->isEmptyRow($cells)) {
                    continue;
                }
                yield $number => $cells;
            }
        } finally {
            fclose($handle);
        }
    }

    public function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii(trim($header)))), '_');
    }

    private function cellValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) ($cell instanceof DateTimeInterface ? 'x' : $cell)) !== '') {
                return false;
            }
        }

        return true;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
