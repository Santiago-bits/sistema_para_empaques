<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\ReportService;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Estadísticas con período seleccionable y comparaciones históricas. */
class StatsController extends Controller
{
    public const PERIODS = ['today' => 'Hoy', 'week' => 'Esta semana', 'month' => 'Este mes', 'season' => 'Temporada', 'range' => 'Rango'];

    public function __invoke(Request $request, ReportService $reports): View
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';
        $today = CarbonImmutable::today();
        $season = Season::current();

        [$from, $to] = match ($period) {
            'today' => [$today, $today->endOfDay()],
            'week' => [$today->startOfWeek(), $today->endOfDay()],
            'season' => $season ? [CarbonImmutable::parse($season->starts_on->toDateString()), $today->endOfDay()] : [$today->startOfYear(), $today->endOfDay()],
            'range' => [null, null],
            default => [$today->startOfMonth(), $today->endOfDay()],
        };

        $f = $period === 'range' ? ReportFilters::fromRequest($request) : ReportFilters::between($from, $to);
        $group = $f->days() > 93 ? ($f->days() > 400 ? 'month' : 'week') : 'day';

        return view('stats.index', [
            'period' => $period,
            'periods' => self::PERIODS,
            'filters' => $f,
            'indicators' => $reports->indicators($f),
            'comparisons' => $reports->comparisons(),
            'metrics' => ReportService::COMPARISON_METRICS,
            'charts' => [
                'production' => array_map(fn ($r) => ['label' => $r['label'], 'kg' => $r['kg'], 'crates' => $r['crates']], $reports->productionByPeriod($f, $group)),
                'packers' => $reports->productionBy('packer', $f, 15),
                'varieties' => $reports->productionBy('variety', $f),
                'sizes' => $reports->productionBy('size', $f),
                'waste' => $reports->rejectsBy('reason', $f),
                'loads' => $reports->dispatchesByDay($f),
                'hourly' => $reports->productionByHour($f),
            ],
        ]);
    }
}
