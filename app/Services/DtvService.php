<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DtvDocument;
use App\Models\Load;
use App\Models\Lot;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Support\Facades\DB;

/**
 * DTV-e (SENASA): alta y corrección de ingresos/egresos con sus líneas, armado automático desde una carga o un
 * lote, y saldo de kilos por especie/variedad (ingresos − egresos).
 */
class DtvService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $header  date, direction, number, doc_type, issuer, establishment, recipient, destination, transport, notes, load_id, lot_id
     * @param  list<array<string, mixed>>  $lines  species, variety_id|variety_name, quantity, unit, kg_per_unit, kg_total
     */
    public function save(?DtvDocument $document, array $header, array $lines, User $by, ?string $reason = null): DtvDocument
    {
        $lines = $this->normalizeLines($lines);
        if ($lines === []) {
            throw new BusinessException('El DTV-e necesita al menos una línea con cantidad.');
        }

        return DB::transaction(function () use ($document, $header, $lines, $by, $reason) {
            $creating = $document === null;
            $document ??= new DtvDocument(['created_by' => $by->id]);
            $before = $creating ? null : ['header' => $document->only(array_keys($header)), 'kg' => (float) $document->lines()->sum('kg_total')];

            $document->fill($header)->save();
            $document->lines()->delete();
            $document->lines()->createMany($lines);

            $this->audit->log($creating ? 'create' : 'update', $document, $before,
                ['number' => $document->number, 'kg' => array_sum(array_column($lines, 'kg_total'))],
                ($creating ? 'Cargó' : 'Corrigió').' el DTV-e '.$document->number.' ('.$document->directionLabel().')', $reason);

            return $document->load('lines');
        });
    }

    public function delete(DtvDocument $document, string $reason): void
    {
        $document->delete();
        $this->audit->log('delete', $document, ['number' => $document->number], null, 'Eliminó el DTV-e '.$document->number, $reason);
    }

    /** Egreso armado desde una carga: una línea por variedad con sus cajones y kilos. */
    public function draftFromLoad(Load $load): array
    {
        $nominal = (float) setting('label.nominal_kg', 18) ?: 18.0;
        $rows = DB::table('crates')->join('varieties', 'varieties.id', '=', 'crates.variety_id')
            ->where('crates.current_load_id', $load->id)->whereNull('crates.deleted_at')
            ->groupBy('crates.variety_id', 'varieties.name', 'varieties.species')
            ->selectRaw('crates.variety_id, varieties.name as variety, varieties.species as species, COUNT(*) as crates')
            ->get();

        return [
            'header' => [
                'date' => ($load->dispatched_at ?? $load->date ?? today())->toDateString(),
                'direction' => 'out', 'number' => (string) ($load->guide_number ?? ''), 'doc_type' => 'EMP-CTC',
                'issuer' => setting('company.legal_name') ?: setting('company.name'),
                'establishment' => setting('label.senasa_number'),
                'recipient' => $load->client?->business_name, 'destination' => $load->destination?->name,
                'transport' => $load->driver?->full_name ?? $load->transporter?->business_name, 'load_id' => $load->id,
            ],
            'lines' => $rows->map(fn ($r) => [
                'species' => $r->species, 'variety_id' => $r->variety_id, 'quantity' => (int) $r->crates, 'unit' => 'Cajón',
                'kg_per_unit' => $nominal, 'kg_total' => round((int) $r->crates * $nominal, 2),
            ])->all(),
        ];
    }

    /** Ingreso armado desde un lote (fruta del productor). */
    public function draftFromLot(Lot $lot): array
    {
        $quantity = (float) ($lot->bins ?? $lot->quantity ?? 0);

        return [
            'header' => [
                'date' => $lot->date?->toDateString() ?? today()->toDateString(), 'direction' => 'in',
                'number' => (string) ($lot->dtv_number ?? ''), 'doc_type' => 'PROD-EMP', 'issuer' => $lot->producer?->name,
                'recipient' => setting('company.name'), 'destination' => setting('company.locality') ?: null,
                'transport' => $lot->driver?->full_name, 'lot_id' => $lot->id,
            ],
            'lines' => [[
                'species' => $lot->variety?->species, 'variety_id' => $lot->variety_id, 'quantity' => $quantity, 'unit' => $lot->bins ? 'Bin' : 'Cajón',
                'kg_per_unit' => $quantity > 0 && $lot->kg_received ? round((float) $lot->kg_received / $quantity, 2) : null,
                'kg_total' => (float) ($lot->kg_received ?? 0),
            ]],
        ];
    }

    /**
     * Saldo por especie y variedad: kilos que entraron, que salieron y diferencia.
     *
     * @return \Illuminate\Support\Collection<int, object{species: ?string, variety: string, kg_in: float, kg_out: float, balance: float}>
     */
    public function balances(?string $from = null, ?string $to = null)
    {
        return DB::table('dtv_lines')
            ->join('dtv_documents', 'dtv_documents.id', '=', 'dtv_lines.dtv_document_id')
            ->leftJoin('varieties', 'varieties.id', '=', 'dtv_lines.variety_id')
            ->whereNull('dtv_documents.deleted_at')
            ->when($from, fn ($q) => $q->whereDate('dtv_documents.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('dtv_documents.date', '<=', $to))
            ->groupByRaw('COALESCE(dtv_lines.species, varieties.species), COALESCE(varieties.name, dtv_lines.variety_name)')
            ->selectRaw("COALESCE(dtv_lines.species, varieties.species) as species, COALESCE(varieties.name, dtv_lines.variety_name) as variety,
                SUM(CASE WHEN dtv_documents.direction = 'in' THEN dtv_lines.kg_total ELSE 0 END) as kg_in,
                SUM(CASE WHEN dtv_documents.direction = 'out' THEN dtv_lines.kg_total ELSE 0 END) as kg_out")
            ->orderByRaw('1, 2')
            ->get()
            ->map(fn ($r) => (object) ['species' => $r->species, 'variety' => (string) $r->variety, 'kg_in' => (float) $r->kg_in,
                'kg_out' => (float) $r->kg_out, 'balance' => round((float) $r->kg_in - (float) $r->kg_out, 2)]);
    }

    /** Líneas válidas: cantidad > 0; kilos = cantidad × kg por unidad (o el total cargado a mano). */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $quantity = (float) parse_number($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $perUnit = isset($line['kg_per_unit']) && $line['kg_per_unit'] !== '' && $line['kg_per_unit'] !== null ? (float) parse_number($line['kg_per_unit']) : null;
            $total = $perUnit !== null ? round($quantity * $perUnit, 2) : abs((float) parse_number($line['kg_total'] ?? 0));
            $varietyId = ! empty($line['variety_id']) ? (int) $line['variety_id'] : null;
            $variety = $varietyId ? Variety::query()->find($varietyId) : null;
            $out[] = [
                'species' => trim((string) ($line['species'] ?? '')) ?: $variety?->species,
                'variety_id' => $variety?->id,
                'variety_name' => $variety ? null : (trim((string) ($line['variety_name'] ?? '')) ?: null),
                'quantity' => $quantity,
                'unit' => trim((string) ($line['unit'] ?? '')) ?: 'Cajón',
                'kg_per_unit' => $perUnit,
                'kg_total' => $total,
            ];
        }

        return $out;
    }
}
