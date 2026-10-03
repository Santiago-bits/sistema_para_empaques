<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\ProductionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionRecordController extends Controller
{
    use CatalogOptions;

    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        $totals = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as records')
            ->selectRaw('SUM(CASE WHEN voided_at IS NULL THEN 1 ELSE 0 END) as crates')
            ->selectRaw('COALESCE(SUM(CASE WHEN voided_at IS NULL THEN weight ELSE 0 END), 0) as kg')
            ->selectRaw('SUM(CASE WHEN authorized_by IS NOT NULL THEN 1 ELSE 0 END) as authorized')
            ->first();

        $records = $query
            ->with('crate:id,code,status', 'packer:id,code,first_name,last_name', 'variety:id,name', 'size:id,name',
                'shift:id,name', 'productionLine:id,name', 'user:id,first_name,last_name', 'authorizer:id,first_name,last_name')
            ->latest('recorded_at')->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('production.index', [
            'records' => $records,
            'totals' => $totals,
            'packers' => $this->packerOptions(),
            'varieties' => $this->varietyOptions(false),
            'sizes' => $this->sizeOptions(false),
            'shifts' => $this->shiftOptions(),
            'lines' => $this->lineOptions(),
            'users' => User::query()->visibleTo()->whereIn('id', ProductionRecord::query()->select('user_id')->distinct())
                ->orderBy('last_name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]),
        ]);
    }

    public function void(Request $request, ProductionRecord $record, ProductionService $production): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $production->voidRecord($record, $data['reason'], $request->user());

        return back()->with('success', 'Registro anulado. El cajón '.$record->crate?->code.' puede registrarse nuevamente.');
    }

    /** Dashboard del embalador: sólo su propia producción (packer_id del usuario). */
    public function mine(Request $request): View
    {
        $user = $request->user();
        $packer = $user->packer;

        if (! $packer) {
            return view('production.mine', ['packer' => null]);
        }

        $from = $request->filled('date_from') ? $request->date('date_from')->startOfDay() : today();
        $to = $request->filled('date_to') ? $request->date('date_to')->endOfDay() : today()->endOfDay();
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Base SIEMPRE restringida al embalador del usuario autenticado.
        $base = fn () => ProductionRecord::query()->valid()->where('packer_id', $packer->id)->whereBetween('recorded_at', [$from, $to]);

        $totals = $base()->toBase()->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();
        $today = ProductionRecord::query()->valid()->where('packer_id', $packer->id)->where('recorded_at', '>=', today())
            ->toBase()->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

        $byVariety = $base()->join('varieties', 'varieties.id', '=', 'production_records.variety_id')
            ->groupBy('varieties.name')->orderByDesc('kg')
            ->selectRaw('varieties.name as label, COUNT(*) as crates, SUM(production_records.weight) as kg')->toBase()->get();
        $bySize = $base()->join('sizes', 'sizes.id', '=', 'production_records.size_id')
            ->groupBy('sizes.name', 'sizes.sort')->orderBy('sizes.sort')
            ->selectRaw('sizes.name as label, COUNT(*) as crates, SUM(production_records.weight) as kg')->toBase()->get();

        $history = ProductionRecord::query()->where('packer_id', $packer->id)
            ->with('crate:id,code', 'variety:id,name', 'size:id,name', 'shift:id,name')
            ->when($request->filled('date_from') || $request->filled('date_to'), fn ($q) => $q->whereBetween('recorded_at', [$from, $to]))
            ->latest('recorded_at')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('production.mine', compact('packer', 'from', 'to', 'totals', 'today', 'byVariety', 'bySize', 'history'));
    }

    private function filtered(Request $request): Builder
    {
        return ProductionRecord::query()
            ->when($request->filled('date_from'), fn ($q) => $q->where('recorded_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('recorded_at', '<=', $request->date('date_to')->endOfDay()))
            ->when($request->filled('crate'), function ($q) use ($request) {
                $term = trim((string) $request->string('crate'));
                $q->whereIn('crate_id', \App\Models\Crate::query()->withTrashed()->select('id')
                    ->where(fn ($w) => $w->where('code', 'like', $term.'%')->orWhere('barcode', $term)));
            })
            ->when($request->filled('packer_id'), fn ($q) => $q->where('packer_id', $request->integer('packer_id')))
            ->when($request->filled('variety_id'), fn ($q) => $q->where('variety_id', $request->integer('variety_id')))
            ->when($request->filled('size_id'), fn ($q) => $q->where('size_id', $request->integer('size_id')))
            ->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->integer('shift_id')))
            ->when($request->filled('production_line_id'), fn ($q) => $q->where('production_line_id', $request->integer('production_line_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->input('state') === 'valid', fn ($q) => $q->whereNull('voided_at'))
            ->when($request->input('state') === 'voided', fn ($q) => $q->whereNotNull('voided_at'))
            ->when($request->input('state') === 'authorized', fn ($q) => $q->whereNotNull('authorized_by'));
    }
}
