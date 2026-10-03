<?php

namespace App\Http\Controllers\Dtv;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Treatment;
use App\Services\Reports\ReportDataset;
use App\Services\Reports\ReportFilters;
use App\Services\TreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Tratamientos de la fruta (antes: hoja «TRATAMIENTO» del Excel): fecha, destino, cantidad, tipo y empresa. */
class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentService $treatments)
    {
    }

    public function index(Request $request): View|Response
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'type' => ['nullable', 'string', 'max:60'],
            'client_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'], 'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
        ]);
        $like = isset($f['q']) ? '%'.addcslashes($f['q'], '%_\\').'%' : null;
        $query = Treatment::query()->with('client')
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
            ->when($like, fn ($q) => $q->where(fn ($w) => $w->where('destination', 'like', $like)->orWhere('provider', 'like', $like)->orWhere('dtv_number', 'like', $like)))
            ->orderByDesc('date')->orderByDesc('id');

        if (! empty($f['format'])) {
            $spec = collect(['FECHA', 'CLIENTE', 'DESTINO', 'CANTIDAD', 'UNIDAD', 'TRATAMIENTO', 'EMPRESA', 'DTV-e', 'OBSERVACIONES'])
                ->mapWithKeys(fn ($h) => [$h => ['label' => $h, 'type' => 'text']])->all();
            $dataset = new ReportDataset('tratamientos', 'Tratamientos', $spec, fn () => (clone $query)->lazyById(500)->map(fn (Treatment $t) => [
                'FECHA' => $t->date->format('d/m/Y'), 'CLIENTE' => $t->client?->business_name, 'DESTINO' => $t->destination,
                'CANTIDAD' => num($t->quantity, 2), 'UNIDAD' => $t->unit, 'TRATAMIENTO' => $t->type, 'EMPRESA' => $t->provider,
                'DTV-e' => $t->dtv_number, 'OBSERVACIONES' => $t->notes,
            ]));

            return app(\App\Services\ExportService::class)->download($dataset, $f['format'], ReportFilters::between(today(), today()), $request->user());
        }

        return view('treatments.index', [
            'treatments' => (clone $query)->paginate($this->perPage($request, 50))->withQueryString(),
            'totals' => $this->treatments->totalsByType($query),
            'types' => Treatment::types(),
            'clients' => Client::query()->orderBy('business_name')->pluck('business_name', 'id'),
        ]);
    }

    public function create(): View
    {
        $last = Treatment::query()->latest('id')->first();

        return view('treatments.form', $this->formData(new Treatment([
            'date' => today(), 'unit' => 'Cajón', 'type' => $last?->type ?? Treatment::types()[0], 'provider' => $last?->provider,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->treatments->save(null, $this->validated($request), $request->user());

        return redirect()->route('treatments.index')->with('success', 'Tratamiento registrado.');
    }

    public function edit(Treatment $treatment): View
    {
        return view('treatments.form', $this->formData($treatment));
    }

    public function update(Request $request, Treatment $treatment): RedirectResponse
    {
        $data = $this->validated($request);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo'])['reason'];
        $this->treatments->save($treatment, $data, $request->user(), $reason);

        return redirect()->route('treatments.index')->with('success', 'Tratamiento corregido.');
    }

    public function destroy(Request $request, Treatment $treatment): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo'])['reason'];
        $this->treatments->delete($treatment, $reason);

        return redirect()->route('treatments.index')->with('success', 'Tratamiento eliminado.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['quantity' => parse_number($request->input('quantity'))]);

        return $request->validate([
            'date' => ['required', 'date'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:20'],
            'type' => ['required', 'string', 'max:60'],
            'provider' => ['nullable', 'string', 'max:120'],
            'dtv_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['quantity' => 'cantidad', 'type' => 'tipo de tratamiento', 'provider' => 'empresa', 'destination' => 'destino']);
    }

    private function formData(Treatment $treatment): array
    {
        return [
            'treatment' => $treatment,
            'types' => Treatment::types(),
            'clients' => Client::query()->where('active', true)->orderBy('business_name')->pluck('business_name', 'id'),
            'destinations' => Treatment::query()->whereNotNull('destination')->distinct()->orderBy('destination')->limit(50)->pluck('destination'),
            'providers' => Treatment::query()->whereNotNull('provider')->distinct()->orderBy('provider')->limit(20)->pluck('provider'),
        ];
    }
}
