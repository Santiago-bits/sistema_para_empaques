<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Crate;
use App\Models\Load;
use App\Models\LoadCrate;
use App\Models\Pallet;
use App\Models\Supply;
use App\Services\ReportService;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultas de sólo lectura (habilidad «read»). */
class ReadController extends ApiController
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id, 'username' => $user->username, 'name' => $user->full_name,
            'role' => $user->role?->name, 'token' => $user->currentAccessToken()?->name,
            'abilities' => $user->currentAccessToken()?->abilities ?? [],
        ]]);
    }

    public function crates(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'crates.view');
        $request->validate(['status' => ['nullable', 'string', 'max:20'], 'code' => ['nullable', 'string', 'max:60'], 'updated_since' => ['nullable', 'date']]);

        $page = Crate::query()->with(['variety:id,name', 'size:id,name', 'packer:id,code', 'pallet:id,code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', addcslashes(mb_strtoupper((string) $request->query('code')), '%_\\').'%'))
            ->when($request->filled('updated_since'), fn ($q) => $q->where('updated_at', '>=', $request->date('updated_since')))
            ->orderByDesc('id')->paginate($this->perPageApi($request));

        return response()->json($this->paginated($page, fn (Crate $c) => $this->crate($c)));
    }

    public function crateShow(Request $request, string $code): JsonResponse
    {
        $this->requirePermission($request, 'crates.view');
        $crate = Crate::query()->with(['variety:id,name', 'size:id,name', 'packer:id,code', 'pallet:id,code'])
            ->where('code', mb_strtoupper($code))->firstOrFail();

        return response()->json(['data' => $this->crate($crate)]);
    }

    public function pallet(Request $request, string $code): JsonResponse
    {
        $this->requirePermission($request, 'pallets.view');
        $pallet = Pallet::query()->with(['producer:id,name', 'lot:id,code', 'variety:id,name'])->withCount('crates')
            ->where('code', mb_strtoupper($code))->firstOrFail();

        return response()->json(['data' => [
            'code' => $pallet->code, 'status' => $pallet->status->value, 'producer' => $pallet->producer?->name,
            'lot' => $pallet->lot?->code, 'variety' => $pallet->variety?->name, 'crates' => $pallet->crates_count,
            'gross_weight_kg' => $pallet->gross_weight !== null ? (float) $pallet->gross_weight : null,
            'received_at' => $pallet->received_at?->toIso8601String(),
        ]]);
    }

    public function loads(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'loads.view');
        $request->validate(['status' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $page = Load::query()->with(['client:id,business_name', 'destination:id,name', 'truck:id,plate'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->date('to')))
            ->orderByDesc('id')->paginate($this->perPageApi($request));

        return response()->json($this->paginated($page, fn (Load $l) => $this->load($l)));
    }

    public function loadShow(Request $request, string $number): JsonResponse
    {
        $this->requirePermission($request, 'loads.view');
        $load = Load::query()->with(['client:id,business_name', 'destination:id,name', 'truck:id,plate'])
            ->where('number', mb_strtoupper($number))->firstOrFail();
        $crates = Crate::query()->whereIn('id', LoadCrate::query()->where('load_id', $load->id)->whereNull('removed_at')->select('crate_id'))->orderBy('code')->pluck('code');

        return response()->json(['data' => $this->load($load) + ['crates' => $crates]]);
    }

    public function supplies(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'supplies.view');

        return response()->json(['data' => Supply::query()->where('active', true)->orderBy('name')->get()->map(fn (Supply $s) => [
            'code' => $s->code, 'name' => $s->name, 'unit' => $s->unit, 'stock' => (float) $s->stock, 'min_stock' => (float) $s->min_stock,
            'low' => (float) $s->min_stock > 0 && (float) $s->stock <= (float) $s->min_stock,
        ])]);
    }

    public function today(Request $request, ReportService $reports): JsonResponse
    {
        $this->requirePermission($request, 'dashboard.view');
        $ind = $reports->indicators(ReportFilters::forDay(today()));

        return response()->json(['data' => [
            'date' => today()->toDateString(),
            'crates_in' => $ind['crates_in'], 'crates_processed' => $ind['crates_processed'], 'kg_processed' => $ind['kg_processed'],
            'packers' => $ind['packers'], 'rejects' => $ind['rejects'], 'waste_pct' => $ind['waste_pct'],
            'loads_dispatched' => $ind['loads_dispatched'], 'kg_dispatched' => $ind['kg_dispatched'],
        ]]);
    }

    private function crate(Crate $crate): array
    {
        return [
            'code' => $crate->code, 'status' => $crate->status->value, 'quality_status' => $crate->quality_status,
            'weight_kg' => $crate->weight !== null ? (float) $crate->weight : null,
            'variety' => $crate->variety?->name, 'size' => $crate->size?->name, 'packer' => $crate->packer?->code,
            'pallet' => $crate->pallet?->code, 'processed_at' => $crate->processed_at?->toIso8601String(),
            'updated_at' => $crate->updated_at?->toIso8601String(),
        ];
    }

    private function load(Load $l): array
    {
        return [
            'number' => $l->number, 'status' => $l->status->value, 'date' => $l->date?->toDateString(),
            'client' => $l->client?->business_name, 'destination' => $l->destination?->name, 'truck' => $l->truck?->plate,
            'total_crates' => (int) $l->total_crates, 'total_kg' => (float) $l->total_kg,
            'dispatched_at' => $l->dispatched_at?->toIso8601String(),
        ];
    }
}
