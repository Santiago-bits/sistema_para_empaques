<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Services\Reports\QuintaYieldService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Rendimiento por quinta: lo que entró de cada productor contra lo que salió empacado. */
class QuintaYieldController extends Controller
{
    public function __invoke(Request $request, QuintaYieldService $service): View
    {
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'producer_id' => ['nullable', 'integer']]);
        $from = $f['from'] ?? today()->startOfYear()->toDateString();
        $to = $f['to'] ?? today()->toDateString();
        $rows = $service->rows($from, $to, isset($f['producer_id']) ? (int) $f['producer_id'] : null);
        $received = (float) $rows->sum('kg_received');

        return view('reports.quintas', [
            'rows' => $rows, 'from' => $from, 'to' => $to,
            'producers' => Producer::query()->orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'bins' => (int) $rows->sum('bins'), 'kg_received' => $received, 'packages' => (int) $rows->sum('packages'),
                'kg_packed' => (float) $rows->sum('kg_packed'), 'kg_rejected' => (float) $rows->sum('kg_rejected'),
                'yield_pct' => $received > 0 ? round($rows->sum('kg_packed') / $received * 100, 1) : null,
            ],
        ]);
    }
}
