<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Load;
use App\Models\ProductionRecord;
use App\Services\LocationService;
use App\Services\ReportService;
use App\Services\Reports\ReportFilters;
use App\Support\CurrentWarehouse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Dashboard: "qué está pasando ahora". Todo se calcula con consultas agregadas y se
 * cachea 60 s por galpón; la pantalla se refresca sola (dashboard.data).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function __invoke(Request $request): View
    {
        return view('dashboard', $this->pageData($request) + ['live' => $this->live()]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(['live' => $this->liveForJson(), 'stats' => $this->stats()]);
    }

    private function pageData(Request $request): array
    {
        $user = $request->user();

        return [
            'stats' => $this->stats(),
            'alerts' => $user->can('alerts.view') ? Alert::query()->open()->latest('updated_at')->limit(8)->get() : collect(),
            'recentLoads' => $user->can('loads.view') && module_enabled('loads')
                ? Load::query()->with(['destination:id,name', 'truck:id,plate'])->latest('updated_at')->limit(6)->get() : collect(),
            'capacity' => module_enabled('locations') ? app(LocationService::class)->capacitySummary() : null,
        ];
    }

    /** Indicadores y series (cacheadas). */
    private function stats(): array
    {
        $warehouse = CurrentWarehouse::id();

        return Cache::remember('dashboard:stats:'.$warehouse.':'.now()->format('YmdHi'), ReportService::DASHBOARD_TTL, function () use ($warehouse) {
            $today = CarbonImmutable::today();
            $day = ReportFilters::forDay($today, $warehouse);
            $week = ReportFilters::between($today->startOfWeek(), $today->endOfDay(), $warehouse);
            $month = ReportFilters::between($today->startOfMonth(), $today->endOfDay(), $warehouse);
            $last14 = ReportFilters::between($today->subDays(13), $today->endOfDay(), $warehouse);

            $ind = $this->reports->indicators($day);
            $comparisons = $this->reports->comparisons($day, ['day', 'week', 'month'], false);
            $crates = $this->reports->crateStatusCounts($warehouse);
            $pallets = $this->reports->palletStatusCounts($warehouse);
            $target = $this->reports->targetKg('daily');

            return [
                'today' => $ind,
                'week_kg' => $this->reports->productionTotals($week)['kg'],
                'month_kg' => $this->reports->productionTotals($month)['kg'],
                'comparisons' => $comparisons,
                'pending_crates' => (int) ($crates['registered'] ?? 0),
                'pallets_available' => (int) ($pallets['with_product'] ?? 0) + (int) ($pallets['received'] ?? 0),
                'pallets_prepared' => (int) ($pallets['reserved'] ?? 0) + (int) ($pallets['loaded'] ?? 0),
                'pending_loads' => $this->reports->pendingLoads($warehouse),
                'target' => ['kg' => $target, 'actual' => $ind['kg_processed'], 'pct' => $target > 0 ? round($ind['kg_processed'] / $target * 100, 1) : null],
                'charts' => [
                    'hourly' => $this->reports->productionByHour($day),
                    'daily' => $this->reports->productionByPeriod($last14, 'day'),
                    'varieties' => $this->reports->productionBy('variety', $day),
                    'sizes' => $this->reports->productionBy('size', $day),
                    'packers' => $this->reports->productionBy('packer', $day, 8),
                ],
                'generated_at' => now()->format('H:i'),
            ];
        });
    }

    /** Últimos escaneos (sin cache: es lo que pasa "ahora"). */
    private function live()
    {
        return ProductionRecord::query()->valid()
            ->with(['crate:id,code', 'packer:id,code,first_name,last_name', 'variety:id,name', 'size:id,name'])
            ->latest('recorded_at')->latest('id')->limit(8)->get();
    }

    private function liveForJson(): array
    {
        return $this->live()->map(fn ($r) => [
            'crate' => $r->crate?->code, 'packer' => $r->packer?->full_name, 'variety' => $r->variety?->name,
            'size' => $r->size?->name, 'weight' => (float) $r->weight, 'time' => $r->recorded_at->format('H:i'),
        ])->all();
    }
}
