<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Destination;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Producer;
use App\Models\ProductionLine;
use App\Models\Reason;
use App\Models\Shift;
use App\Models\Size;
use App\Models\Variety;
use App\Support\CurrentWarehouse;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Filtros de un reporte (inmutables y serializables).
 *
 * Se construyen desde la request (GET) y se reutilizan EXACTAMENTE igual para
 * la vista, las exportaciones síncronas y el Job de exportación en cola: así
 * lo que se exporta es siempre lo que el usuario está viendo.
 */
final class ReportFilters
{
    /** Filtros por ID admitidos. */
    public const ID_KEYS = [
        'variety_id', 'size_id', 'packer_id', 'producer_id', 'client_id', 'destination_id', 'lot_id', 'load_id',
        'shift_id', 'production_line_id', 'reason_id',
    ];

    public const GROUPS = ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año'];

    /** Rango máximo consultable (evita consultas desmedidas por URL). */
    public const MAX_DAYS = 1100;

    /**
     * @param  array<string, int>  $ids
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly array $ids = [],
        public readonly string $group = 'day',
        public readonly ?int $warehouseId = null,
        public readonly ?string $status = null,
        public readonly ?string $lotCode = null,
        public readonly ?string $loadNumber = null,
    ) {
    }

    public static function fromRequest(Request $request, int $defaultDays = 30): self
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'group' => ['nullable', 'in:'.implode(',', array_keys(self::GROUPS))],
            'status' => ['nullable', 'string', 'max:20'],
            'lot' => ['nullable', 'string', 'max:30'],
            'load' => ['nullable', 'string', 'max:30'],
            ...array_fill_keys(self::ID_KEYS, ['nullable', 'integer', 'min:1']),
        ]);

        $today = CarbonImmutable::today();
        $from = ! empty($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : $today->subDays($defaultDays - 1);
        $to = ! empty($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : $today->endOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['to' => 'La fecha "hasta" debe ser posterior a la fecha "desde".']);
        }
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['from' => 'El rango máximo de consulta es de 3 años.']);
        }

        $ids = [];
        foreach (self::ID_KEYS as $key) {
            if (! empty($data[$key])) {
                $ids[$key] = (int) $data[$key];
            }
        }

        // Lote y carga se pueden filtrar por código (más práctico que un select con miles de opciones).
        $lotCode = isset($data['lot']) ? trim($data['lot']) : null;
        if ($lotCode !== null && $lotCode !== '' && ! isset($ids['lot_id'])) {
            $ids['lot_id'] = (int) (Lot::query()->where('code', $lotCode)->value('id') ?? 0);
        }
        $loadNumber = isset($data['load']) ? trim($data['load']) : null;
        if ($loadNumber !== null && $loadNumber !== '' && ! isset($ids['load_id'])) {
            $ids['load_id'] = (int) (Load::query()->where('number', $loadNumber)->value('id') ?? 0);
        }

        return new self(
            $from,
            $to,
            $ids,
            $data['group'] ?? 'day',
            CurrentWarehouse::id(),
            $data['status'] ?? null,
            $lotCode ?: null,
            $loadNumber ?: null,
        );
    }

    /** Filtro de un único día (cierre diario, dashboard). */
    public static function forDay(CarbonInterface $day, ?int $warehouseId = null): self
    {
        $d = CarbonImmutable::parse($day->toDateString());

        return new self($d->startOfDay(), $d->endOfDay(), [], 'day', $warehouseId ?? CurrentWarehouse::id());
    }

    public static function between(CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null, array $ids = []): self
    {
        return new self(
            CarbonImmutable::parse($from->format('Y-m-d H:i:s')),
            CarbonImmutable::parse($to->format('Y-m-d H:i:s')),
            $ids,
            'day',
            $warehouseId ?? CurrentWarehouse::id(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            array_map('intval', $data['ids'] ?? []),
            $data['group'] ?? 'day',
            $data['warehouse_id'] ?? null,
            $data['status'] ?? null,
            $data['lot_code'] ?? null,
            $data['load_number'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->format('Y-m-d H:i:s'),
            'to' => $this->to->format('Y-m-d H:i:s'),
            'ids' => $this->ids,
            'group' => $this->group,
            'warehouse_id' => $this->warehouseId,
            'status' => $this->status,
            'lot_code' => $this->lotCode,
            'load_number' => $this->loadNumber,
        ];
    }

    public function get(string $key): ?int
    {
        return $this->ids[$key] ?? null;
    }

    public function has(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (isset($this->ids[$key])) {
                return true;
            }
        }

        return false;
    }

    /** Copia con otro rango de fechas (mismos filtros). */
    public function withRange(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self(
            CarbonImmutable::parse($from->format('Y-m-d H:i:s')),
            CarbonImmutable::parse($to->format('Y-m-d H:i:s')),
            $this->ids, $this->group, $this->warehouseId, $this->status, $this->lotCode, $this->loadNumber,
        );
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function cacheKey(): string
    {
        return md5(json_encode($this->toArray()));
    }

    /**
     * Descripción legible de los filtros aplicados (encabezado del PDF, auditoría).
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        $out = ['Período' => $this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')];

        $resolvers = [
            'variety_id' => ['Variedad', fn ($id) => Variety::query()->whereKey($id)->value('name')],
            'size_id' => ['Tamaño', fn ($id) => Size::query()->whereKey($id)->value('name')],
            'packer_id' => ['Embalador', fn ($id) => ($p = Packer::withTrashed()->find($id)) ? $p->code.' — '.$p->full_name : null],
            'producer_id' => ['Productor', fn ($id) => Producer::withTrashed()->whereKey($id)->value('name')],
            'client_id' => ['Cliente', fn ($id) => Client::withTrashed()->whereKey($id)->value('business_name')],
            'destination_id' => ['Destino', fn ($id) => Destination::withTrashed()->whereKey($id)->value('name')],
            'lot_id' => ['Lote', fn ($id) => Lot::withTrashed()->whereKey($id)->value('code') ?? $this->lotCode],
            'load_id' => ['Carga', fn ($id) => Load::withTrashed()->whereKey($id)->value('number') ?? $this->loadNumber],
            'shift_id' => ['Turno', fn ($id) => Shift::query()->whereKey($id)->value('name')],
            'production_line_id' => ['Línea', fn ($id) => ProductionLine::query()->whereKey($id)->value('name')],
            'reason_id' => ['Motivo', fn ($id) => Reason::query()->whereKey($id)->value('name')],
        ];

        foreach ($this->ids as $key => $id) {
            if (isset($resolvers[$key])) {
                [$label, $resolver] = $resolvers[$key];
                $out[$label] = (string) ($resolver($id) ?? 'Sin coincidencias');
            }
        }
        if ($this->status) {
            $out['Estado'] = $this->status;
        }

        return $out;
    }
}
