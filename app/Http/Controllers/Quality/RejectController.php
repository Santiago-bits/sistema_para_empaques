<?php

namespace App\Http\Controllers\Quality;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\RejectRequest;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Reason;
use App\Models\Size;
use App\Models\Variety;
use App\Services\QualityService;
use App\Services\WasteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class RejectController extends Controller
{
    private const FILTERS = ['variety_id', 'size_id', 'lot_id', 'packer_id', 'reason_id'];

    public function __construct(
        private readonly WasteService $waste,
        private readonly QualityService $quality,
    ) {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to] = $this->period($request);
        $filters = array_filter($request->only(self::FILTERS));

        $rejects = $this->waste->rejectsQuery($from, $to, $filters)
            ->with(['reason:id,name', 'variety:id,name', 'size:id,name', 'lot:id,code', 'crate:id,code', 'user:id,first_name,last_name',
                'packer:id,code,first_name,last_name'])
            ->latest('rejected_at')->latest('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $byVariety = $this->waste->byVariety($from, $to, $filters);
        $byReason = $this->waste->byReason($from, $to, $filters);

        return view('rejects.index', [
            'rejects' => $rejects,
            'from' => $from,
            'to' => $to,
            'summary' => $this->waste->summary($from, $to, $filters),
            'byVariety' => $byVariety,
            'byLot' => array_slice($this->waste->byLot($from, $to, $filters), 0, 10),
            'byPacker' => array_slice($this->waste->byPacker($from, $to, $filters), 0, 10),
            'byReason' => $byReason,
            'charts' => [
                'variety' => ['labels' => array_column($byVariety, 'label'), 'kg' => array_column($byVariety, 'kg')],
                'reason' => ['labels' => array_column($byReason, 'label'), 'kg' => array_column($byReason, 'kg')],
            ],
        ] + $this->catalogs());
    }

    public function create(): View
    {
        return view('rejects.create', $this->catalogs());
    }

    public function store(RejectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['crate_code'])) {
            $code = trim($data['crate_code']);
            $crate = Crate::query()->where('code', $code)->orWhere('barcode', $code)->first();
            if (! $crate) {
                throw new BusinessException("No existe un cajón con el código {$code}.");
            }
            $data['crate_id'] = $crate->id;
        }
        unset($data['crate_code']);

        $reject = $this->quality->registerReject($data);

        return redirect()->route('rejects.index')
            ->with('success', 'Rechazo registrado: '.kg($reject->weight).'.');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->subDays(29)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function catalogs(): array
    {
        return [
            'varieties' => Variety::query()->orderBy('name')->pluck('name', 'id'),
            'sizes' => Size::query()->orderBy('sort')->orderBy('name')->pluck('name', 'id'),
            'lots' => Lot::query()->latest('date')->limit(300)->pluck('code', 'id'),
            'packers' => Packer::query()->orderBy('last_name')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->full_name]),
            'reasons' => Reason::query()->where('type', 'reject')->where('active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
