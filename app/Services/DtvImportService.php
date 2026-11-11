<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DtvDocument;
use App\Models\User;
use App\Models\Variety;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa el registro de DTV-e desde:
 * - la planilla que ya usa el galpón (hoja «DTV-e»: FECHA, E / I, N° DTV-e, TIPO, EMISOR, ESTABLECIMIENTO, DESTINATARIO,
 *   DESTINO, ESPECIE, VARIEDAD, CANT., UNIDAD, KG, KG TOTALES, TRANSPORTE, OBSERVACIONES), o
 * - el listado de movimientos que se descarga de ARCA (# Movimiento, Tipo, Origen, Destino, Fecha de emisión, Fecha de
 *   carga, Fecha de vencimiento, Estado, Requiere intervención), que no trae especies ni kilos.
 * Encuentra sola la fila de títulos (puede haber logo arriba) y agrupa las filas por número de DTV. Los que ya están
 * cargados no se repiten: sólo se les actualiza lo que viene de ARCA (estado, fechas, intervención) y lo que estaba vacío.
 */
class DtvImportService
{
    private const COLUMNS = [
        'fecha' => 'date', 'fecha de emision' => 'date', 'fecha emision' => 'date',
        'e / i' => 'direction', 'e/i' => 'direction', 'ingreso / egreso' => 'direction',
        'n° dtv-e' => 'number', 'nº dtv-e' => 'number', 'n dtv-e' => 'number', 'dtv-e' => 'number', 'numero' => 'number',
        '# movimiento' => 'number', 'movimiento' => 'number', 'n° movimiento' => 'number', 'nro movimiento' => 'number',
        'tipo' => 'doc_type', 'emisor' => 'issuer', 'establecimiento' => 'establishment', 'origen' => 'establishment',
        'destinatario' => 'recipient', 'destino' => 'destination', 'especie' => 'species', 'variedad' => 'variety',
        'cant.' => 'quantity', 'cant' => 'quantity', 'cantidad' => 'quantity', 'unidad' => 'unit', 'kg' => 'kg_per_unit',
        'kg totales' => 'kg_total', 'kg total' => 'kg_total', 'transporte' => 'transport', 'observaciones' => 'notes',
        'fecha de carga' => 'loaded_on', 'fecha carga' => 'loaded_on',
        'fecha de vencimiento' => 'expires_on', 'vencimiento' => 'expires_on', 'fecha vencimiento' => 'expires_on',
        'estado' => 'arca_status', 'requiere intervencion' => 'needs_intervention', 'intervencion' => 'needs_intervention',
    ];

    /** Lo que se actualiza en un DTV-e que ya estaba cargado (viene de ARCA y cambia con el tiempo). */
    private const ARCA_FIELDS = ['loaded_on', 'expires_on', 'arca_status', 'needs_intervention'];

    public function __construct(private readonly ImportService $reader, private readonly AuditService $audit)
    {
    }

    /** @return array{documents: int, lines: int, updated: int, skipped: int, errors: list<string>} */
    public function import(string $path, User $by): array
    {
        $map = null;
        $groups = [];
        $errors = [];
        foreach ($this->reader->readRows($path) as $number => $cells) {
            if ($map === null) {
                $map = $this->headerMap($cells);
                continue;
            }
            $row = [];
            foreach ($map as $index => $field) {
                $row[$field] = $cells[$index] ?? null;
            }
            if (blank($row['number'] ?? null) && blank($row['quantity'] ?? null)) {
                continue;
            }
            $date = $this->date($row['date'] ?? null);
            $docNumber = $this->number($row['number'] ?? null);
            if (! $date || $docNumber === null) {
                $errors[] = 'Fila '.$number.': falta la fecha o el número de DTV.';
                continue;
            }
            $type = $this->text($row['doc_type'] ?? null, 20);
            $type = $type === null ? null : Str::upper($type);
            $direction = $this->direction((string) ($row['direction'] ?? ''), $type, (float) parse_number($row['kg_total'] ?? 0));
            $key = $direction.'|'.$docNumber;
            $groups[$key] ??= ['header' => [
                'date' => $date, 'direction' => $direction, 'number' => $docNumber, 'doc_type' => $type,
                'issuer' => $this->text($row['issuer'] ?? null, 120), 'establishment' => $this->text($row['establishment'] ?? null, 40),
                'recipient' => $this->text($row['recipient'] ?? null, 160), 'destination' => $this->text($row['destination'] ?? null, 160),
                'transport' => $this->text($row['transport'] ?? null, 160), 'notes' => $this->text($row['notes'] ?? null, 1000),
                'loaded_on' => $this->date($row['loaded_on'] ?? null), 'expires_on' => $this->date($row['expires_on'] ?? null),
                'arca_status' => $this->status($row['arca_status'] ?? null),
                'needs_intervention' => array_key_exists('needs_intervention', $row) ? $this->yes($row['needs_intervention']) : null,
            ], 'lines' => []];

            // Sin cantidad (listado de ARCA): queda el documento sin líneas.
            $quantity = abs((float) parse_number($row['quantity'] ?? 0));
            if ($quantity <= 0) {
                continue;
            }
            $perUnit = blank($row['kg_per_unit'] ?? null) ? null : abs((float) parse_number($row['kg_per_unit']));
            $groups[$key]['lines'][] = [
                'species' => $this->text($row['species'] ?? null, 60),
                'variety' => $this->text($row['variety'] ?? null, 80),
                'quantity' => $quantity,
                'unit' => $this->text($row['unit'] ?? null, 20) ?? 'Cajón',
                'kg_per_unit' => $perUnit,
                'kg_total' => blank($row['kg_total'] ?? null) ? round($quantity * (float) $perUnit, 2) : abs((float) parse_number($row['kg_total'])),
            ];
        }
        if ($map === null) {
            throw new BusinessException('No se encontró la fila de títulos (FECHA o Fecha de emisión, N° DTV-e o # Movimiento, …) en la planilla.');
        }

        $varieties = Variety::query()->get(['id', 'name'])->mapWithKeys(fn ($v) => [Str::lower(Str::ascii($v->name)) => $v->id]);
        $created = 0;
        $lines = 0;
        $updated = 0;
        $skipped = 0;
        DB::transaction(function () use ($groups, $varieties, $by, &$created, &$lines, &$updated, &$skipped) {
            foreach ($groups as $group) {
                $header = $group['header'];
                $existing = DtvDocument::query()->where('direction', $header['direction'])->where('number', $header['number'])->lockForUpdate()->first();
                if ($existing) {
                    // Lo de ARCA se pisa (el estado cambia); el resto sólo se completa si estaba vacío.
                    $changes = [];
                    foreach ($header as $field => $value) {
                        if ($value === null) {
                            continue;
                        }
                        if (in_array($field, self::ARCA_FIELDS, true) || blank($existing->getAttribute($field))) {
                            $changes[$field] = $value;
                        }
                    }
                    $existing->fill($changes);
                    if ($existing->isDirty()) {
                        $existing->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }
                $header['needs_intervention'] = (bool) $header['needs_intervention'];
                $document = DtvDocument::query()->create($header + ['created_by' => $by->id]);
                foreach ($group['lines'] as $line) {
                    $varietyId = $line['variety'] ? ($varieties[Str::lower(Str::ascii($line['variety']))] ?? null) : null;
                    $document->lines()->create([
                        'species' => $line['species'], 'variety_id' => $varietyId, 'variety_name' => $varietyId ? null : $line['variety'],
                        'quantity' => $line['quantity'], 'unit' => $line['unit'], 'kg_per_unit' => $line['kg_per_unit'], 'kg_total' => $line['kg_total'],
                    ]);
                    $lines++;
                }
                $created++;
            }
        });
        $this->audit->log('import', null, null, ['documents' => $created, 'lines' => $lines, 'updated' => $updated, 'skipped' => $skipped],
            'Importó '.$created.' DTV-e desde planilla'.($updated ? ' y actualizó '.$updated : ''));

        return ['documents' => $created, 'lines' => $lines, 'updated' => $updated, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 50)];
    }

    /** @return array<int, string>|null índice de columna => campo, si la fila es la de títulos */
    private function headerMap(array $cells): ?array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $name = $this->headerName((string) $cell);
            if (isset(self::COLUMNS[$name]) && ! in_array(self::COLUMNS[$name], $map, true)) {
                $map[$index] = self::COLUMNS[$name];
            }
        }

        return in_array('date', $map, true) && in_array('number', $map, true) ? $map : null;
    }

    /** «Fecha de Emisión» → «fecha de emision» (sin tildes ni mayúsculas; el «°» se respeta). */
    private function headerName(string $cell): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $cell) ?? ''));

        return strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }

    /** Número de DTV / movimiento sin espacios de más ni ceros adelante («0014606819» → «14606819»). */
    private function number(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }
        $text = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
        $text = ltrim($text, '0');

        return $text === '' ? null : mb_substr($text, 0, 30);
    }

    private function status(mixed $value): ?string
    {
        $text = Str::lower(Str::ascii(trim((string) $value)));

        return match (true) {
            $text === '' => null,
            str_starts_with($text, 'cerr') => 'closed',
            str_starts_with($text, 'anul') => 'voided',
            str_starts_with($text, 'emit') => 'issued',
            default => null,
        };
    }

    private function yes(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(Str::ascii(trim((string) $value))), ['si', 's', 'yes', 'x', '1', 'true', 'verdadero'], true);
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (is_numeric($text) && (float) $text > 20000) { // número de serie de Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $text)->toDateString();
        }
        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** E / I de la planilla; si no está, se deduce del tipo (EMP-… sale del empaque, …-EMP entra). */
    private function direction(string $value, ?string $type, float $kgTotal): string
    {
        $value = Str::lower(Str::ascii(trim($value)));
        if (str_starts_with($value, 'e')) {
            return str_starts_with($value, 'eg') ? 'out' : 'in';
        }
        if (str_starts_with($value, 'i')) {
            return 'in';
        }
        if (str_starts_with($value, 's')) {
            return 'out';
        }
        if ($type !== null && str_contains($type, '-')) {
            if (str_starts_with($type, 'EMP-')) {
                return 'out';
            }
            if (str_ends_with($type, '-EMP')) {
                return 'in';
            }
        }

        return $kgTotal < 0 ? 'out' : 'in';
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
