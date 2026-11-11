<?php

namespace App\Http\Controllers\Dtv;

use App\Http\Controllers\Controller;
use App\Models\DtvDocument;
use App\Models\DtvLine;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Variety;
use App\Services\DtvImportService;
use App\Services\DtvService;
use App\Services\Reports\ReportDataset;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Registro de DTV-e (SENASA): lo que antes era la hoja «DTV-e» del Excel, con saldo de kilos por variedad. */
class DtvController extends Controller
{
    public function __construct(private readonly DtvService $dtv)
    {
    }

    public function index(Request $request): View|Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'direction' => ['nullable', Rule::in(array_keys(DtvDocument::DIRECTIONS))],
            'q' => ['nullable', 'string', 'max:100'], 'species' => ['nullable', 'string', 'max:60'], 'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
            // Origen y destino del tipo (EMP-CTC → de EMP a CTC), transporte, establecimiento y estado en ARCA.
            'origin' => ['nullable', 'string', 'max:10', 'alpha_dash'], 'target' => ['nullable', 'string', 'max:10', 'alpha_dash'],
            'doc_type' => ['nullable', 'string', 'max:20'], 'transport' => ['nullable', 'string', 'max:160'],
            'establishment' => ['nullable', 'string', 'max:40'], 'arca_status' => ['nullable', Rule::in(array_keys(DtvDocument::ARCA_STATUSES))],
            'intervention' => ['nullable', Rule::in(['yes', 'no'])],
        ]);
        $lines = $this->linesQuery($filters);

        if (! empty($filters['format'])) {
            return $this->export($request, $lines, $filters['format']);
        }

        $totals = (clone $lines)->toBase()->cloneWithout(['columns', 'orders', 'limit', 'offset'])->cloneWithoutBindings(['select', 'order'])
            ->selectRaw("SUM(CASE WHEN dtv_documents.direction = 'in' THEN dtv_lines.kg_total ELSE 0 END) as kg_in,
                SUM(CASE WHEN dtv_documents.direction = 'out' THEN dtv_lines.kg_total ELSE 0 END) as kg_out,
                COUNT(DISTINCT dtv_documents.id) as documents")->first();

        return view('dtv.index', [
            'lines' => $lines->paginate($this->perPage($request, 50))->withQueryString(),
            'totals' => $totals,
            'balances' => $this->dtv->balances($filters['from'] ?? null, $filters['to'] ?? null),
            'species' => DtvLine::query()->whereNotNull('species')->distinct()->orderBy('species')->pluck('species', 'species'),
            // Para los filtros: los transportes, establecimientos y tipos que ya se usaron en este galpón.
            'transports' => DtvDocument::query()->whereNotNull('transport')->distinct()->orderBy('transport')->pluck('transport', 'transport'),
            'establishments' => DtvDocument::query()->whereNotNull('establishment')->distinct()->orderBy('establishment')->pluck('establishment', 'establishment'),
            'docTypes' => DtvDocument::query()->whereNotNull('doc_type')->distinct()->orderBy('doc_type')->pluck('doc_type', 'doc_type')
                ->union(DtvDocument::DOC_TYPES)->except('OTRO')->sortKeys(),
        ]);
    }

    public function create(Request $request): View
    {
        $draft = ['header' => ['date' => today()->toDateString(), 'direction' => $request->query('direction') === 'in' ? 'in' : 'out',
            'doc_type' => 'EMP-CTC', 'issuer' => setting('company.legal_name') ?: setting('company.name'), 'establishment' => setting('label.senasa_number')],
            'lines' => [['unit' => 'Cajón', 'kg_per_unit' => setting('label.nominal_kg', 18)]]];
        if ($request->filled('load') && ($load = Load::query()->find($request->integer('load')))) {
            $draft = $this->dtv->draftFromLoad($load->load('client', 'destination', 'driver', 'transporter'));
        } elseif ($request->filled('lot') && ($lot = Lot::query()->find($request->integer('lot')))) {
            $draft = $this->dtv->draftFromLot($lot->load('producer', 'variety', 'driver'));
        }

        return view('dtv.form', $this->formData(new DtvDocument($draft['header']), $draft['lines']));
    }

    public function store(Request $request): RedirectResponse
    {
        [$header, $lines] = $this->validated($request);
        $document = $this->dtv->save(null, $header, $lines, $request->user());

        return redirect()->route('dtv.show', $document)->with('success', 'DTV-e '.$document->number.' guardado.');
    }

    public function show(DtvDocument $dtv): View
    {
        return view('dtv.show', ['document' => $dtv->load('lines.variety', 'relatedLoad', 'lot')]);
    }

    public function edit(DtvDocument $dtv): View
    {
        $dtv->load('lines');

        return view('dtv.form', $this->formData($dtv, $dtv->lines->map(fn ($l) => $l->only(['species', 'variety_id', 'variety_name', 'quantity', 'unit', 'kg_per_unit', 'kg_total']))->all()));
    }

    public function update(Request $request, DtvDocument $dtv): RedirectResponse
    {
        [$header, $lines] = $this->validated($request, $dtv);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo'])['reason'];
        $this->dtv->save($dtv, $header, $lines, $request->user(), $reason);

        return redirect()->route('dtv.show', $dtv)->with('success', 'DTV-e corregido.');
    }

    public function destroy(Request $request, DtvDocument $dtv): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo'])['reason'];
        $this->dtv->delete($dtv, $reason);

        return redirect()->route('dtv.index')->with('success', 'DTV-e eliminado.');
    }

    public function import(Request $request, DtvImportService $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt']], [], ['file' => 'planilla']);
        $path = $request->file('file')->getRealPath();
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $target = $path.'.'.$ext; // el lector decide por la extensión
        copy($path, $target);
        try {
            $result = $importer->import($target, $request->user());
        } finally {
            @unlink($target);
        }
        $message = 'Se importaron '.$result['documents'].' DTV-e ('.$result['lines'].' líneas).'
            .($result['updated'] ? ' A '.$result['updated'].' que ya estaban cargados se les actualizó el estado de ARCA.' : '')
            .($result['skipped'] ? ' '.$result['skipped'].' ya estaban cargados y no se repitieron.' : '');

        return redirect()->route('dtv.index')->with('success', $message)->with('import_errors', $result['errors']);
    }

    /** Líneas de DTV con los datos de su documento (una fila por línea, como en la planilla). */
    private function linesQuery(array $f)
    {
        // Desde el documento (con sus líneas si tiene): los que vienen del listado de ARCA pueden no tener líneas.
        return DtvDocument::query()
            ->leftJoin('dtv_lines', 'dtv_lines.dtv_document_id', '=', 'dtv_documents.id')
            ->leftJoin('varieties', 'varieties.id', '=', 'dtv_lines.variety_id')
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('dtv_documents.date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('dtv_documents.date', '<=', $v))
            ->when($f['direction'] ?? null, fn ($q, $v) => $q->where('dtv_documents.direction', $v))
            ->when($f['species'] ?? null, fn ($q, $v) => $q->where('dtv_lines.species', $v))
            ->when($f['origin'] ?? null, fn ($q, $v) => $q->where('dtv_documents.doc_type', 'like', strtoupper($v).'-%'))
            ->when($f['target'] ?? null, fn ($q, $v) => $q->where('dtv_documents.doc_type', 'like', '%-'.strtoupper($v)))
            ->when($f['doc_type'] ?? null, fn ($q, $v) => $q->where('dtv_documents.doc_type', $v))
            ->when($f['transport'] ?? null, fn ($q, $v) => $q->where('dtv_documents.transport', $v))
            ->when($f['establishment'] ?? null, fn ($q, $v) => $q->where('dtv_documents.establishment', $v))
            ->when($f['arca_status'] ?? null, fn ($q, $v) => $q->where('dtv_documents.arca_status', $v))
            ->when(($f['intervention'] ?? null) === 'yes', fn ($q) => $q->where('dtv_documents.needs_intervention', true))
            ->when(($f['intervention'] ?? null) === 'no', fn ($q) => $q->where('dtv_documents.needs_intervention', false))
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('dtv_documents.number', 'like', $like)->orWhere('dtv_documents.recipient', 'like', $like)
                    ->orWhere('dtv_documents.destination', 'like', $like)->orWhere('dtv_documents.transport', 'like', $like)
                    ->orWhere('dtv_documents.issuer', 'like', $like)->orWhere('dtv_lines.variety_name', 'like', $like)->orWhere('varieties.name', 'like', $like));
            })
            ->orderByDesc('dtv_documents.date')->orderByDesc('dtv_documents.id')->orderBy('dtv_lines.id')
            ->select('dtv_documents.*', 'dtv_documents.id as dtv_document_id', 'dtv_lines.species', 'dtv_lines.variety_id',
                'dtv_lines.variety_name', 'dtv_lines.quantity', 'dtv_lines.unit', 'dtv_lines.kg_per_unit', 'dtv_lines.kg_total',
                'varieties.name as variety_label');
    }

    private function export(Request $request, $lines, string $format): Response
    {
        $spec = [];
        foreach (['FECHA', 'E / I', 'N° DTV-e', 'TIPO', 'EMISOR', 'ESTABLECIMIENTO', 'DESTINATARIO', 'DESTINO', 'ESPECIE', 'VARIEDAD', 'CANT.', 'UNIDAD', 'KG', 'KG TOTALES', 'TRANSPORTE', 'OBSERVACIONES',
            'FECHA DE CARGA', 'VENCIMIENTO', 'ESTADO', 'REQUIERE INTERVENCIÓN'] as $h) {
            $spec[$h] = ['label' => $h, 'type' => 'text'];
        }
        $dataset = new ReportDataset('dtv-e', 'DTV-e', $spec, fn () => (clone $lines)->cursor()->map(fn ($l) => [
            'FECHA' => \Illuminate\Support\Carbon::parse($l->date)->format('d/m/Y'),
            'E / I' => $l->direction === 'out' ? 'Egresos' : 'Ingresos',
            'N° DTV-e' => $l->number, 'TIPO' => $l->doc_type, 'EMISOR' => $l->issuer, 'ESTABLECIMIENTO' => $l->establishment,
            'DESTINATARIO' => $l->recipient, 'DESTINO' => $l->destination, 'ESPECIE' => $l->species,
            'VARIEDAD' => $l->variety_label ?? $l->variety_name, 'CANT.' => $l->quantity !== null ? num($l->quantity, 2) : null, 'UNIDAD' => $l->unit,
            'KG' => $l->kg_per_unit !== null ? num($l->kg_per_unit, 2) : null,
            'KG TOTALES' => $l->kg_total !== null ? num(($l->direction === 'out' ? -1 : 1) * (float) $l->kg_total, 2) : null,
            'TRANSPORTE' => $l->transport, 'OBSERVACIONES' => $l->notes,
            'FECHA DE CARGA' => $l->loaded_on ? \Illuminate\Support\Carbon::parse($l->loaded_on)->format('d/m/Y') : null,
            'VENCIMIENTO' => $l->expires_on ? \Illuminate\Support\Carbon::parse($l->expires_on)->format('d/m/Y') : null,
            'ESTADO' => $l->arca_status ? (DtvDocument::ARCA_STATUSES[$l->arca_status] ?? $l->arca_status) : null,
            'REQUIERE INTERVENCIÓN' => $l->needs_intervention ? 'Sí' : 'No',
        ]));

        return app(\App\Services\ExportService::class)->download($dataset, $format, ReportFilters::between(today(), today()), $request->user());
    }

    /** @return array{0: array, 1: list<array>} */
    private function validated(Request $request, ?DtvDocument $document = null): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'direction' => ['required', Rule::in(array_keys(DtvDocument::DIRECTIONS))],
            'number' => ['required', 'string', 'max:30', Rule::unique('dtv_documents', 'number')->where('direction', $request->input('direction'))->whereNull('deleted_at')->ignore($document?->id)],
            'doc_type' => ['nullable', 'string', 'max:20'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'establishment' => ['nullable', 'string', 'max:40'],
            'recipient' => ['nullable', 'string', 'max:160'],
            'destination' => ['nullable', 'string', 'max:160'],
            'transport' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'loaded_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'arca_status' => ['nullable', Rule::in(array_keys(DtvDocument::ARCA_STATUSES))],
            'needs_intervention' => ['nullable', 'boolean'],
            'load_id' => ['nullable', 'integer', 'exists:loads,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'lines' => ['nullable', 'array', 'max:50'],
            'lines.*.species' => ['nullable', 'string', 'max:60'],
            'lines.*.variety_id' => ['nullable', 'integer', 'exists:varieties,id'],
            'lines.*.variety_name' => ['nullable', 'string', 'max:80'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.kg_per_unit' => ['nullable'],
            'lines.*.kg_total' => ['nullable'],
        ], ['number.unique' => 'Ya hay un DTV-e con ese número.'], ['number' => 'número de DTV-e', 'direction' => 'ingreso o egreso', 'lines' => 'líneas']);

        $data['needs_intervention'] = $request->boolean('needs_intervention');
        $data['doc_type'] = isset($data['doc_type']) ? strtoupper(trim($data['doc_type'])) : null;
        // La validación arma el arreglo por regla, no por fila: se vuelve al orden en que se cargaron.
        $lines = $data['lines'] ?? [];
        ksort($lines);
        unset($data['lines']);

        return [$data, $lines];
    }

    private function formData(DtvDocument $document, array $lines): array
    {
        return [
            'document' => $document,
            'lines' => $lines ?: [['unit' => 'Cajón']],
            'varieties' => Variety::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'species']),
            'docTypes' => DtvDocument::DOC_TYPES,
        ];
    }
}
