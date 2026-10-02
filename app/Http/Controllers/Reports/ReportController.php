<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Destination;
use App\Models\Packer;
use App\Models\Producer;
use App\Models\ProductionLine;
use App\Models\Reason;
use App\Models\Shift;
use App\Models\Size;
use App\Models\Variety;
use App\Enums\LoadStatus;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Services\Reports\ReportDatasets;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes con filtros. La misma instancia de ReportFilters alimenta la pantalla y la
 * exportación (Excel/CSV/PDF), así lo exportado es exactamente lo que se está viendo.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportDatasets $datasets,
        private readonly ReportService $reports,
        private readonly ExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        $f = ReportFilters::fromRequest($request);

        return view('reports.index', [
            'reports' => collect(ReportDatasets::REPORTS)
                ->reject(fn ($r, $key) => $key === 'executive' && ! $request->user()->can('reports.view'))
                ->map(fn ($r) => $r[0]),
            'indicators' => $this->reports->indicators($f),
            'filters' => $f,
        ]);
    }

    public function show(Request $request, string $report): View|Response|RedirectResponse
    {
        abort_unless(isset(ReportDatasets::REPORTS[$report]), 404);
        $f = ReportFilters::fromRequest($request);

        if ($report === 'packer' && ! $f->get('packer_id')) {
            return view('reports.pick-packer', [
                'packers' => Packer::withTrashed()->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->full_name]),
                'filters' => $f,
            ]);
        }

        $variant = (string) $request->query('variant', 'summary');
        $format = $request->query('format');
        if ($format) {
            return $this->export($request, $report, $variant, $f, (string) $format);
        }

        $dataset = $this->datasets->make($report, 'summary', $f, $request->user());

        return view('reports.show', [
            'report' => $report,
            'title' => ReportDatasets::title($report),
            'variants' => ReportDatasets::REPORTS[$report][2],
            'visibleFilters' => ReportDatasets::REPORTS[$report][1],
            'dataset' => $dataset,
            'rows' => collect($dataset->rows())->take(500)->all(),
            'chart' => $this->chartFor($report, $f),
            'filters' => $f,
            'options' => $this->filterOptions(ReportDatasets::REPORTS[$report][1]),
            'packer' => $report === 'packer' ? Packer::withTrashed()->find($f->get('packer_id')) : null,
            'packerTotals' => $report === 'packer' ? $this->reports->productionTotals($f) : null,
        ]);
    }

    private function export(Request $request, string $report, string $variant, ReportFilters $f, string $format): Response|RedirectResponse
    {
        $permission = $format === 'pdf' ? 'reports.export_pdf' : 'reports.export_excel';
        abort_unless($request->user()->can($permission), 403);

        $response = $this->exports->export($request->user(), $report, $variant, $f, $format);
        if ($response === null) {
            return back()->with('info', 'El reporte es grande: se está generando en segundo plano. Te avisaremos en Notificaciones cuando esté listo.');
        }

        return $response;
    }

    /** Descarga de exportaciones generadas en segundo plano (sólo del propio usuario). */
    public function download(Request $request, string $file): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9\-_.]+\.(xlsx|csv|pdf)$/', $file) === 1, 404);
        $path = ExportService::pathFor($request->user()->id, $file);
        abort_unless(Storage::disk(ExportService::DISK)->exists($path), 404);

        return Storage::disk(ExportService::DISK)->download($path, $file);
    }

    /** Datos de gráfico adecuados a cada reporte. */
    private function chartFor(string $report, ReportFilters $f): ?array
    {
        return match ($report) {
            'production' => ['type' => 'bar', 'label' => 'Kg', 'rows' => array_map(fn ($r) => ['label' => $r['label'], 'value' => $r['kg']], $this->reports->productionByPeriod($f))],
            'packers' => $this->dimensionChart('packer', $f, 15),
            'packer' => $this->dimensionChart('variety', $f),
            'varieties' => $this->dimensionChart('variety', $f, null, 'doughnut'),
            'sizes' => $this->dimensionChart('size', $f),
            'producers' => $this->dimensionChart('producer', $f, 15),
            'loads' => ['type' => 'bar', 'label' => 'Kg despachados', 'rows' => array_map(fn ($r) => ['label' => $r['label'], 'value' => $r['kg']], $this->reports->dispatchesByDay($f))],
            'waste' => ['type' => 'doughnut', 'label' => 'Kg', 'rows' => array_map(fn ($r) => ['label' => $r['label'], 'value' => $r['kg']], $this->reports->rejectsBy('reason', $f))],
            default => null,
        };
    }

    private function dimensionChart(string $dimension, ReportFilters $f, ?int $limit = null, string $type = 'bar'): array
    {
        return ['type' => $type, 'label' => 'Kg', 'rows' => array_map(fn ($r) => ['label' => $r['label'], 'value' => $r['kg']], $this->reports->productionBy($dimension, $f, $limit))];
    }

    private function filterOptions(array $visible): array
    {
        $loaders = [
            'variety_id' => fn () => Variety::query()->orderBy('name')->pluck('name', 'id'),
            'size_id' => fn () => Size::query()->orderBy('sort')->pluck('name', 'id'),
            'packer_id' => fn () => Packer::withTrashed()->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->full_name]),
            'producer_id' => fn () => Producer::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'client_id' => fn () => Client::withTrashed()->orderBy('business_name')->pluck('business_name', 'id'),
            'destination_id' => fn () => Destination::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'shift_id' => fn () => Shift::query()->orderBy('starts_at')->pluck('name', 'id'),
            'production_line_id' => fn () => ProductionLine::query()->orderBy('code')->pluck('name', 'id'),
            'reason_id' => fn () => Reason::query()->where('type', 'reject')->orderBy('name')->pluck('name', 'id'),
            'status' => fn () => collect(LoadStatus::options()),
            'group' => fn () => collect(ReportFilters::GROUPS),
        ];

        $options = [];
        foreach ($visible as $key) {
            if (isset($loaders[$key])) {
                $options[$key] = $loaders[$key]();
            }
        }

        return $options;
    }
}
