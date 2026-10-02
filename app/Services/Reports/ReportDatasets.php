<?php

namespace App\Services\Reports;

use App\Enums\LoadStatus;
use App\Models\Packer;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Definición tabular (columnas + filas) de cada reporte. La MISMA definición
 * alimenta la tabla en pantalla, la exportación Excel/CSV/PDF y el Job en cola,
 * por lo que la exportación respeta exactamente los filtros activos.
 */
class ReportDatasets
{
    /**
     * Catálogo de reportes: clave => [título, filtros visibles, variantes exportables].
     */
    public const REPORTS = [
        'production' => ['Producción', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'client_id', 'destination_id', 'load', 'shift_id', 'production_line_id', 'group'], ['summary' => 'Resumen por período', 'detail' => 'Detalle de registros']],
        'packers' => ['Producción por embalador', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'shift_id', 'production_line_id'], ['summary' => 'Resumen']],
        'packer' => ['Informe de embalador', ['variety_id', 'size_id', 'producer_id', 'lot'], ['summary' => 'Por variedad', 'detail' => 'Detalle de registros']],
        'varieties' => ['Producción por variedad', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'client_id', 'destination_id', 'load'], ['summary' => 'Resumen']],
        'sizes' => ['Producción por tamaño', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'client_id', 'destination_id', 'load'], ['summary' => 'Resumen']],
        'producers' => ['Producción por productor', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'client_id', 'destination_id', 'load'], ['summary' => 'Resumen']],
        'loads' => ['Cargas y despachos', ['client_id', 'destination_id', 'load', 'status'], ['summary' => 'Detalle de cargas', 'destinations' => 'Por destino']],
        'waste' => ['Rechazos y merma', ['variety_id', 'size_id', 'packer_id', 'producer_id', 'lot', 'reason_id'], ['summary' => 'Por motivo', 'detail' => 'Detalle de rechazos']],
        'executive' => ['Reporte ejecutivo', ['client_id'], ['summary' => 'Resumen']],
    ];

    public function __construct(private readonly ReportService $reports)
    {
    }

    public static function title(string $report): string
    {
        return self::REPORTS[$report][0] ?? 'Reporte';
    }

    public function make(string $report, string $variant, ReportFilters $f, ?User $user = null): ReportDataset
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        if (! isset(self::REPORTS[$report][2][$variant])) {
            $variant = 'summary';
        }

        return match ($report) {
            'production' => $variant === 'detail' ? $this->productionDetail($f, 'production') : $this->productionSummary($f),
            'packers' => $this->packers($f),
            'packer' => $variant === 'detail' ? $this->productionDetail($f, 'packer') : $this->packer($f),
            'varieties' => $this->byDimension('varieties', 'variety', 'Variedad', $f),
            'sizes' => $this->byDimension('sizes', 'size', 'Tamaño', $f),
            'producers' => $this->byDimension('producers', 'producer', 'Productor', $f),
            'loads' => $variant === 'destinations' ? $this->loadsByDestination($f) : $this->loads($f),
            'waste' => $variant === 'detail' ? $this->wasteDetail($f) : $this->wasteSummary($f),
            'executive' => $this->executive($f, $user),
        };
    }

    private function productionSummary(ReportFilters $f): ReportDataset
    {
        $rows = array_map(fn ($r) => [
            'period' => $r['label'],
            'crates' => $r['crates'],
            'kg' => $r['kg'],
            'avg_kg' => $r['crates'] > 0 ? round($r['kg'] / $r['crates'], 2) : 0,
        ], $this->reports->productionByPeriod($f, $f->group, false));

        return new ReportDataset('production', 'Producción por '.mb_strtolower(ReportFilters::GROUPS[$f->group] ?? 'día'), [
            'period' => ['label' => ReportFilters::GROUPS[$f->group] ?? 'Período', 'type' => 'text'],
            'crates' => ['label' => 'Cajones', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
            'avg_kg' => ['label' => 'Kg prom./cajón', 'type' => 'decimal'],
        ], $rows, null, [
            'period' => 'Total',
            'crates' => array_sum(array_column($rows, 'crates')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
            'avg_kg' => null,
        ]);
    }

    /** Consulta de detalle de producción (paginable o recorrible con lazyById). */
    public function productionDetailQuery(ReportFilters $f): Builder
    {
        return $this->reports->productionQuery($f, true)
            ->leftJoin('packers as p', 'p.id', '=', 'pr.packer_id')
            ->leftJoin('varieties as v', 'v.id', '=', 'pr.variety_id')
            ->leftJoin('sizes as s', 's.id', '=', 'pr.size_id')
            ->leftJoin('lots as lt', 'lt.id', '=', 'c.lot_id')
            ->leftJoin('producers as pd', 'pd.id', '=', 'c.producer_id')
            ->leftJoin('shifts as sh', 'sh.id', '=', 'pr.shift_id')
            ->leftJoin('production_lines as pl', 'pl.id', '=', 'pr.production_line_id')
            ->select([
                'pr.id', 'pr.recorded_at', 'pr.crate_id', 'c.code as crate_code', 'pr.packer_id', 'p.code as packer_code',
                'p.first_name', 'p.last_name', 'v.name as variety', 's.name as size', 'lt.code as lot', 'pd.name as producer',
                'sh.name as shift', 'pl.name as line', 'pr.weight', 'pr.weight_source',
            ]);
    }

    public const PRODUCTION_DETAIL_COLUMNS = [
        'recorded_at' => ['label' => 'Fecha y hora', 'type' => 'datetime'],
        'crate' => ['label' => 'Cajón', 'type' => 'code'],
        'packer_code' => ['label' => 'Cód. embalador', 'type' => 'code'],
        'packer' => ['label' => 'Embalador', 'type' => 'text'],
        'variety' => ['label' => 'Variedad', 'type' => 'text'],
        'size' => ['label' => 'Tamaño', 'type' => 'text'],
        'lot' => ['label' => 'Lote', 'type' => 'code'],
        'producer' => ['label' => 'Productor', 'type' => 'text'],
        'shift' => ['label' => 'Turno', 'type' => 'text'],
        'line' => ['label' => 'Línea', 'type' => 'text'],
        'weight' => ['label' => 'Peso (kg)', 'type' => 'decimal'],
        'source' => ['label' => 'Origen del peso', 'type' => 'text'],
    ];

    public function mapProductionRow(object $r): array
    {
        return [
            'id' => $r->id,
            'recorded_at' => $r->recorded_at,
            'crate' => $r->crate_code,
            'packer_code' => $r->packer_code,
            'packer' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
            'variety' => $r->variety,
            'size' => $r->size,
            'lot' => $r->lot,
            'producer' => $r->producer,
            'shift' => $r->shift,
            'line' => $r->line,
            'weight' => (float) $r->weight,
            'source' => $r->weight_source === 'scale' ? 'Balanza' : 'Manual',
        ];
    }

    private function productionDetail(ReportFilters $f, string $key): ReportDataset
    {
        return new ReportDataset($key, self::title($key).' — detalle', self::PRODUCTION_DETAIL_COLUMNS,
            fn () => $this->productionDetailQuery($f)->lazyById(1000, 'pr.id', 'id')->map(fn ($r) => $this->mapProductionRow($r)),
            fn () => $this->reports->productionQuery($f)->count(), [], 'detail');
    }

    private function packers(ReportFilters $f): ReportDataset
    {
        $hours = $this->reports->productiveHoursByPacker($f);
        $rejected = $this->reports->rejectedKgByPacker($f);
        $rows = array_map(function ($r) use ($hours, $rejected) {
            $h = $hours[$r['id']] ?? 0;
            $rej = $rejected[$r['id']] ?? 0.0;
            $base = $r['kg'] + $rej;

            return [
                'id' => $r['id'],
                'code' => $r['code'],
                'packer' => $r['label'],
                'crates' => $r['crates'],
                'kg' => $r['kg'],
                'avg_kg' => $r['avg_kg'],
                'share' => $r['share'],
                'hours' => $h,
                'crates_per_hour' => $h > 0 ? round($r['crates'] / $h, 1) : null,
                'rejected_kg' => $rej,
                'waste_pct' => $base > 0 ? round($rej / $base * 100, 2) : 0,
            ];
        }, $this->reports->productionBy('packer', $f));

        return new ReportDataset('packers', 'Producción por embalador', [
            'code' => ['label' => 'Código', 'type' => 'text'],
            'packer' => ['label' => 'Embalador', 'type' => 'text'],
            'crates' => ['label' => 'Cajones', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
            'avg_kg' => ['label' => 'Kg prom./cajón', 'type' => 'decimal'],
            'share' => ['label' => '% del total', 'type' => 'pct'],
            'hours' => ['label' => 'Horas prod.', 'type' => 'int'],
            'crates_per_hour' => ['label' => 'Cajones/hora', 'type' => 'decimal'],
            'rejected_kg' => ['label' => 'Kg rechazados', 'type' => 'kg'],
            'waste_pct' => ['label' => '% merma', 'type' => 'pct'],
        ], $rows, null, [
            'code' => 'Total', 'packer' => count($rows).' embaladores',
            'crates' => array_sum(array_column($rows, 'crates')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
            'rejected_kg' => round(array_sum(array_column($rows, 'rejected_kg')), 2),
        ]);
    }

    private function packer(ReportFilters $f): ReportDataset
    {
        $packer = Packer::withTrashed()->find($f->get('packer_id'));
        $rows = array_map(fn ($r) => [
            'variety' => $r['label'],
            'crates' => $r['crates'],
            'kg' => $r['kg'],
            'avg_kg' => $r['avg_kg'],
            'share' => $r['share'],
        ], $this->reports->productionBy('variety', $f));

        return new ReportDataset('packer', 'Embalador '.($packer ? $packer->code.' — '.$packer->full_name : ''), [
            'variety' => ['label' => 'Variedad', 'type' => 'text'],
            'crates' => ['label' => 'Cajones', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
            'avg_kg' => ['label' => 'Kg prom./cajón', 'type' => 'decimal'],
            'share' => ['label' => '% del total', 'type' => 'pct'],
        ], $rows, null, [
            'variety' => 'Total',
            'crates' => array_sum(array_column($rows, 'crates')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
        ]);
    }

    private function byDimension(string $key, string $dimension, string $label, ReportFilters $f): ReportDataset
    {
        $rows = array_map(fn ($r) => [
            'code' => $r['code'],
            'label' => $r['label'],
            'crates' => $r['crates'],
            'kg' => $r['kg'],
            'avg_kg' => $r['avg_kg'],
            'share' => $r['share'],
        ], $this->reports->productionBy($dimension, $f));

        return new ReportDataset($key, self::title($key), [
            'code' => ['label' => 'Código', 'type' => 'text'],
            'label' => ['label' => $label, 'type' => 'text'],
            'crates' => ['label' => 'Cajones', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
            'avg_kg' => ['label' => 'Kg prom./cajón', 'type' => 'decimal'],
            'share' => ['label' => '% del total', 'type' => 'pct'],
        ], $rows, null, [
            'code' => 'Total', 'label' => '',
            'crates' => array_sum(array_column($rows, 'crates')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
            'share' => $rows ? 100 : 0,
        ]);
    }

    /** Consulta de detalle de cargas. */
    public function loadsDetailQuery(ReportFilters $f): Builder
    {
        $diff = $this->reports->secondsDiffExpr('l.created_at', 'l.closed_at');

        return $this->reports->loadsQuery($f)
            ->leftJoin('clients as cl', 'cl.id', '=', 'l.client_id')
            ->leftJoin('destinations as d', 'd.id', '=', 'l.destination_id')
            ->leftJoin('trucks as t', 't.id', '=', 'l.truck_id')
            ->select(['l.id', 'l.number', 'l.date', 'l.status', 'cl.business_name as client', 'd.name as destination',
                't.plate', 'l.total_crates', 'l.total_kg', 'l.created_at', 'l.closed_at', 'l.dispatched_at'])
            ->selectRaw("CASE WHEN l.closed_at IS NULL THEN NULL ELSE {$diff} END as seconds");
    }

    public const LOADS_DETAIL_COLUMNS = [
        'number' => ['label' => 'Carga', 'type' => 'code'],
        'date' => ['label' => 'Fecha', 'type' => 'date'],
        'status' => ['label' => 'Estado', 'type' => 'text'],
        'client' => ['label' => 'Cliente', 'type' => 'text'],
        'destination' => ['label' => 'Destino', 'type' => 'text'],
        'plate' => ['label' => 'Patente', 'type' => 'code'],
        'crates' => ['label' => 'Cajones', 'type' => 'int'],
        'kg' => ['label' => 'Kg', 'type' => 'kg'],
        'closed_at' => ['label' => 'Cerrada', 'type' => 'datetime'],
        'dispatched_at' => ['label' => 'Despachada', 'type' => 'datetime'],
        'minutes' => ['label' => 'Tiempo de armado', 'type' => 'minutes'],
    ];

    public function mapLoadRow(object $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->number,
            'date' => $r->date,
            'status' => LoadStatus::tryFrom($r->status)?->label() ?? $r->status,
            'status_enum' => LoadStatus::tryFrom($r->status),
            'client' => $r->client,
            'destination' => $r->destination,
            'plate' => $r->plate,
            'crates' => (int) $r->total_crates,
            'kg' => (float) $r->total_kg,
            'closed_at' => $r->closed_at,
            'dispatched_at' => $r->dispatched_at,
            'minutes' => $r->seconds !== null ? round((float) $r->seconds / 60, 1) : null,
        ];
    }

    private function loads(ReportFilters $f): ReportDataset
    {
        return new ReportDataset('loads', 'Cargas y despachos', self::LOADS_DETAIL_COLUMNS,
            fn () => $this->loadsDetailQuery($f)->lazyById(500, 'l.id', 'id')->map(fn ($r) => $this->mapLoadRow($r)),
            fn () => $this->reports->loadsQuery($f)->count(), [], 'summary');
    }

    private function loadsByDestination(ReportFilters $f): ReportDataset
    {
        $rows = array_map(fn ($r) => [
            'destination' => $r['label'], 'loads' => $r['loads'], 'trucks' => $r['trucks'], 'crates' => $r['crates'], 'kg' => $r['kg'],
        ], $this->reports->loadsByDestination($f));

        return new ReportDataset('loads', 'Cargas por destino', [
            'destination' => ['label' => 'Destino', 'type' => 'text'],
            'loads' => ['label' => 'Cargas', 'type' => 'int'],
            'trucks' => ['label' => 'Camiones', 'type' => 'int'],
            'crates' => ['label' => 'Cajones', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
        ], $rows, null, [
            'destination' => 'Total',
            'loads' => array_sum(array_column($rows, 'loads')),
            'crates' => array_sum(array_column($rows, 'crates')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
        ], 'destinations');
    }

    private function wasteSummary(ReportFilters $f): ReportDataset
    {
        $rows = array_map(fn ($r) => [
            'reason' => $r['label'], 'count' => $r['count'], 'kg' => $r['kg'], 'share' => $r['share'],
        ], $this->reports->rejectsBy('reason', $f));

        return new ReportDataset('waste', 'Rechazos por motivo', [
            'reason' => ['label' => 'Motivo', 'type' => 'text'],
            'count' => ['label' => 'Rechazos', 'type' => 'int'],
            'kg' => ['label' => 'Kg', 'type' => 'kg'],
            'share' => ['label' => '% del total', 'type' => 'pct'],
        ], $rows, null, [
            'reason' => 'Total',
            'count' => array_sum(array_column($rows, 'count')),
            'kg' => round(array_sum(array_column($rows, 'kg')), 2),
        ]);
    }

    /** Consulta de detalle de rechazos. */
    public function rejectsDetailQuery(ReportFilters $f): Builder
    {
        return $this->reports->rejectsQuery($f)
            ->leftJoin('crates as c', 'c.id', '=', 'r.crate_id')
            ->leftJoin('lots as lt', 'lt.id', '=', 'r.lot_id')
            ->leftJoin('varieties as v', 'v.id', '=', 'r.variety_id')
            ->leftJoin('sizes as s', 's.id', '=', 'r.size_id')
            ->leftJoin('packers as p', 'p.id', '=', 'r.packer_id')
            ->leftJoin('reasons as rs', 'rs.id', '=', 'r.reason_id')
            ->select(['r.id', 'r.rejected_at', 'c.code as crate', 'lt.code as lot', 'v.name as variety', 's.name as size',
                'p.first_name', 'p.last_name', 'rs.name as reason', 'r.weight', 'r.notes']);
    }

    public const REJECTS_DETAIL_COLUMNS = [
        'rejected_at' => ['label' => 'Fecha y hora', 'type' => 'datetime'],
        'crate' => ['label' => 'Cajón', 'type' => 'code'],
        'lot' => ['label' => 'Lote', 'type' => 'code'],
        'variety' => ['label' => 'Variedad', 'type' => 'text'],
        'size' => ['label' => 'Tamaño', 'type' => 'text'],
        'packer' => ['label' => 'Embalador', 'type' => 'text'],
        'reason' => ['label' => 'Motivo', 'type' => 'text'],
        'weight' => ['label' => 'Kg', 'type' => 'decimal'],
        'notes' => ['label' => 'Observaciones', 'type' => 'text'],
    ];

    public function mapRejectRow(object $r): array
    {
        return [
            'id' => $r->id,
            'rejected_at' => $r->rejected_at,
            'crate' => $r->crate,
            'lot' => $r->lot,
            'variety' => $r->variety,
            'size' => $r->size,
            'packer' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')) ?: null,
            'reason' => $r->reason,
            'weight' => (float) $r->weight,
            'notes' => $r->notes,
        ];
    }

    private function wasteDetail(ReportFilters $f): ReportDataset
    {
        return new ReportDataset('waste', 'Rechazos — detalle', self::REJECTS_DETAIL_COLUMNS,
            fn () => $this->rejectsDetailQuery($f)->lazyById(1000, 'r.id', 'id')->map(fn ($r) => $this->mapRejectRow($r)),
            fn () => $this->reports->rejectsQuery($f)->count(), [], 'detail');
    }

    /**
     * Reporte ejecutivo. Las secciones financieras dependen de los permisos del usuario.
     */
    public function executiveSections(ReportFilters $f, ?User $user): array
    {
        $ind = $this->reports->indicators($f);
        $loads = $this->reports->dispatchTotals($f);
        $sections = [
            'production' => ['title' => 'Producción', 'items' => [
                ['Cajones ingresados', $ind['crates_in'], 'int'],
                ['Cajones procesados', $ind['crates_processed'], 'int'],
                ['Kg procesados', $ind['kg_processed'], 'kg'],
                ['Kg promedio por cajón', $ind['avg_kg_per_crate'], 'decimal'],
                ['Cajones por hora', $ind['crates_per_hour'], 'decimal'],
                ['Kg por hora', $ind['kg_per_hour'], 'decimal'],
                ['Embaladores activos', $ind['packers'], 'int'],
            ]],
            'waste' => ['title' => 'Merma', 'items' => [
                ['Rechazos', $ind['rejects'], 'int'],
                ['Kg rechazados', $ind['kg_rejected'], 'kg'],
                ['% merma', $ind['waste_pct'], 'pct'],
                ['% aprovechamiento', $ind['yield_pct'], 'pct'],
            ]],
            'dispatch' => ['title' => 'Despachos', 'items' => [
                ['Cargas despachadas', $loads['loads'], 'int'],
                ['Camiones', $loads['trucks'], 'int'],
                ['Destinos', $loads['destinations'], 'int'],
                ['Cajones despachados', $loads['crates'], 'int'],
                ['Kg despachados', $loads['kg'], 'kg'],
                ['Tiempo promedio de carga', $ind['avg_load_minutes'], 'minutes'],
                ['Cargas pendientes (actual)', $ind['pending_loads'], 'int'],
            ]],
        ];

        if ($user?->can('costs.view')) {
            $costs = $this->reports->costTotals($f);
            $items = [['Costo total', $costs['total'], 'money']];
            foreach ($costs['by_category'] as $cat) {
                $items[] = [$cat['label'], $cat['total'], 'money'];
            }
            foreach ($costs['other_currencies'] as $cur => $amount) {
                $items[] = ['Costos en '.$cur.' (no incluidos)', $amount, 'decimal'];
            }
            $sections['costs'] = ['title' => 'Costos', 'items' => $items];
        }
        if ($user?->can('billing.view')) {
            $billing = $this->reports->billingTotals($f);
            $sections['billing'] = ['title' => 'Facturación', 'items' => [
                ['Comprobantes autorizados', $billing['count'], 'int'],
                ['Facturado neto', $billing['net'], 'money'],
                ['Facturado total (con IVA)', $billing['total'], 'money'],
                ['Comprobantes pendientes', $billing['pending'], 'int'],
            ]];
        }
        if ($user?->can('profit.view')) {
            $profit = $this->reports->profitability($f);
            $sections['profit'] = ['title' => 'Rentabilidad', 'items' => [
                ['Ingresos (neto)', $profit['revenue'], 'money'],
                ['Costos', $profit['costs'], 'money'],
                ['Margen', $profit['margin'], 'money'],
                ['Rentabilidad', $profit['margin_pct'], 'pct'],
                ['Costo por kg', $profit['cost_per_kg'], 'money'],
                ['Ingreso por kg', $profit['revenue_per_kg'], 'money'],
            ]];
        }

        return $sections;
    }

    private function executive(ReportFilters $f, ?User $user): ReportDataset
    {
        $rows = [];
        foreach ($this->executiveSections($f, $user) as $section) {
            foreach ($section['items'] as [$label, $value, $type]) {
                $rows[] = ['section' => $section['title'], 'indicator' => $label, 'value' => Format::display($value, $type)];
            }
        }

        return new ReportDataset('executive', 'Reporte ejecutivo', [
            'section' => ['label' => 'Sección', 'type' => 'text'],
            'indicator' => ['label' => 'Indicador', 'type' => 'text'],
            'value' => ['label' => 'Valor', 'type' => 'text'],
        ], $rows);
    }

    /** Hoy (para etiquetas de nombres de archivo). */
    public static function stamp(): string
    {
        return CarbonImmutable::now()->format('Ymd-His');
    }
}
