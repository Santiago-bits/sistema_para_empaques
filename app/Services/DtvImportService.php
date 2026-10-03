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
 * Importa el registro de DTV-e desde la planilla que ya usa el galpón (hoja «DTV-e»: FECHA, E / I, N° DTV-e,
 * TIPO, EMISOR, ESTABLECIMIENTO, DESTINATARIO, DESTINO, ESPECIE, VARIEDAD, CANT., UNIDAD, KG, KG TOTALES,
 * TRANSPORTE, OBSERVACIONES). Encuentra sola la fila de títulos (puede haber logo arriba), agrupa las filas por
 * número de DTV y no repite los que ya están cargados.
 */
class DtvImportService
{
    private const COLUMNS = [
        'fecha' => 'date', 'e / i' => 'direction', 'e/i' => 'direction', 'ingreso / egreso' => 'direction',
        'n° dtv-e' => 'number', 'nº dtv-e' => 'number', 'n dtv-e' => 'number', 'dtv-e' => 'number', 'numero' => 'number', 'número' => 'number',
        'tipo' => 'doc_type', 'emisor' => 'issuer', 'establecimiento' => 'establishment', 'destinatario' => 'recipient',
        'destino' => 'destination', 'especie' => 'species', 'variedad' => 'variety', 'cant.' => 'quantity', 'cant' => 'quantity',
        'cantidad' => 'quantity', 'unidad' => 'unit', 'kg' => 'kg_per_unit', 'kg totales' => 'kg_total', 'kg total' => 'kg_total',
        'transporte' => 'transport', 'observaciones' => 'notes',
    ];

    public function __construct(private readonly ImportService $reader, private readonly AuditService $audit)
    {
    }

    /** @return array{documents: int, lines: int, skipped: int, errors: list<string>} */
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
            $direction = $this->direction((string) ($row['direction'] ?? ''), (float) parse_number($row['kg_total'] ?? 0));
            $quantity = abs((float) parse_number($row['quantity'] ?? 0));
            if (! $date || blank($row['number'] ?? null) || $quantity <= 0) {
                $errors[] = 'Fila '.$number.': falta la fecha, el número de DTV o la cantidad.';
                continue;
            }
            $key = $direction.'|'.trim((string) $row['number']);
            $groups[$key] ??= ['header' => [
                'date' => $date, 'direction' => $direction, 'number' => trim((string) $row['number']),
                'doc_type' => $this->text($row['doc_type'] ?? null, 20), 'issuer' => $this->text($row['issuer'] ?? null, 120),
                'establishment' => $this->text($row['establishment'] ?? null, 40), 'recipient' => $this->text($row['recipient'] ?? null, 160),
                'destination' => $this->text($row['destination'] ?? null, 160), 'transport' => $this->text($row['transport'] ?? null, 160),
                'notes' => $this->text($row['notes'] ?? null, 1000),
            ], 'lines' => []];
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
            throw new BusinessException('No se encontró la fila de títulos (FECHA, E / I, N° DTV-e, …) en la planilla.');
        }

        $varieties = Variety::query()->get(['id', 'name'])->mapWithKeys(fn ($v) => [Str::lower(Str::ascii($v->name)) => $v->id]);
        $created = 0;
        $lines = 0;
        $skipped = 0;
        DB::transaction(function () use ($groups, $varieties, $by, &$created, &$lines, &$skipped) {
            foreach ($groups as $group) {
                $exists = DtvDocument::query()->where('direction', $group['header']['direction'])->where('number', $group['header']['number'])->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }
                $document = DtvDocument::query()->create($group['header'] + ['created_by' => $by->id]);
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
        $this->audit->log('import', null, null, ['documents' => $created, 'lines' => $lines, 'skipped' => $skipped],
            'Importó '.$created.' DTV-e desde planilla');

        return ['documents' => $created, 'lines' => $lines, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 50)];
    }

    /** @return array<int, string>|null índice de columna => campo, si la fila es la de títulos */
    private function headerMap(array $cells): ?array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $name = Str::lower(trim(preg_replace('/\s+/', ' ', (string) $cell)));
            if (isset(self::COLUMNS[$name]) && ! in_array(self::COLUMNS[$name], $map, true)) {
                $map[$index] = self::COLUMNS[$name];
            }
        }

        return in_array('date', $map, true) && in_array('number', $map, true) && in_array('quantity', $map, true) ? $map : null;
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

    private function direction(string $value, float $kgTotal): string
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

        return $kgTotal < 0 ? 'out' : 'in';
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
