<?php

namespace App\Http\Controllers\Crates;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Production\Concerns\CatalogOptions;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\CrateService;
use App\Services\Devices\DeviceManager;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Etiquetas imprimibles (Code128 + QR). Selección por ids, rango de códigos,
 * pallet o los filtros del listado de cajones. Sin parámetros muestra el formulario.
 */
class LabelController extends Controller
{
    use CatalogOptions;

    public function __construct(private readonly LabelService $labels, private readonly DeviceManager $devices)
    {
    }

    public function crates(Request $request): Response
    {
        $data = $this->validateCommon($request) + $request->validate([
            'from' => ['nullable', 'string', 'max:40'],
            'to' => ['nullable', 'string', 'max:40'],
            'pallet_id' => ['nullable', 'integer'],
        ]);

        $hasSelection = ! empty($data['ids']) || ! empty($data['from']) || ! empty($data['pallet_id']) || $request->boolean('filtered');
        if (! $hasSelection) {
            return response()->view('labels.form', $this->formData('crates'));
        }

        $query = Crate::query()->with('lot', 'variety', 'size', 'pallet', 'producer');
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (! empty($data['from'])) {
            $query->whereBetween('code', [trim($data['from']), trim($data['to'] ?? $data['from'])]);
        } elseif (! empty($data['pallet_id'])) {
            $query->where('pallet_id', $data['pallet_id']);
        } else {
            CrateController::applyFilters($query, $request);
        }

        $crates = $query->orderBy('code')->limit(LabelService::MAX_LABELS + 1)->get();
        if ($crates->isEmpty()) {
            return redirect()->route('labels.crates')->with('error', 'No se encontraron cajones para imprimir.');
        }
        if ($crates->count() > LabelService::MAX_LABELS) {
            return redirect()->route('labels.crates')->with('error', 'Son más de '.LabelService::MAX_LABELS.' etiquetas: acotá la selección.');
        }

        $copies = $data['copies'] ?? 1;
        $labels = [];
        foreach ($crates as $crate) {
            $label = $this->labels->crateLabel($crate);
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $label;
            }
        }

        return $this->devices->labelPrinter()->render($labels, [
            'width' => $data['width'] ?? setting('labels.width', 100),
            'height' => $data['height'] ?? setting('labels.height', 50),
            'title' => 'Etiquetas de cajones ('.count($crates).')',
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('crates.index'),
        ]);
    }

    public function pallets(Request $request): Response
    {
        $data = $this->validateCommon($request);
        if (empty($data['ids'])) {
            return response()->view('labels.form', $this->formData('pallets'));
        }

        $pallets = Pallet::query()->with('lot', 'variety', 'producer', 'owner')->whereIn('id', $data['ids'])
            ->orderBy('code')->limit(LabelService::MAX_LABELS)->get();
        if ($pallets->isEmpty()) {
            return redirect()->route('pallets.index')->with('error', 'No se encontraron pallets para imprimir.');
        }

        $copies = $data['copies'] ?? 1;
        $labels = [];
        foreach ($pallets as $pallet) {
            $label = $this->labels->palletLabel($pallet);
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $label;
            }
        }

        return $this->devices->labelPrinter()->render($labels, [
            'width' => $data['width'] ?? setting('labels.width', 100),
            'height' => $data['height'] ?? setting('labels.height', 50),
            'title' => 'Etiquetas de pallets ('.$pallets->count().')',
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('pallets.index'),
        ]);
    }

    /** Genera N cajones nuevos con numeración correlativa y abre sus etiquetas. */
    public function generate(Request $request, CrateService $crates): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.LabelService::MAX_LABELS],
            'pallet_id' => ['nullable', 'integer', Rule::exists('pallets', 'id')->whereNull('deleted_at')],
            'lot_id' => ['nullable', 'integer', Rule::exists('lots', 'id')->whereNull('deleted_at')],
            'variety_id' => ['nullable', 'integer', Rule::exists('varieties', 'id')],
            'size_id' => ['nullable', 'integer', Rule::exists('sizes', 'id')],
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')],
            'container_type_id' => ['nullable', 'integer', Rule::exists('container_types', 'id')->whereNull('deleted_at')],
        ], [], ['quantity' => 'cantidad', 'pallet_id' => 'pallet', 'lot_id' => 'lote', 'variety_id' => 'variedad',
            'size_id' => 'calibre', 'grade_id' => 'selección', 'container_type_id' => 'envase']);

        $created = $crates->createBatch((int) $data['quantity'], $data, $request->user());

        return redirect()->route('labels.crates', ['from' => $created->first()->code, 'to' => $created->last()->code])
            ->with('success', "Se generaron {$created->count()} cajones ({$created->first()->code} a {$created->last()->code}).");
    }

    private function validateCommon(Request $request): array
    {
        return $request->validate([
            'ids' => ['nullable', 'array', 'max:'.LabelService::MAX_LABELS],
            'ids.*' => ['integer'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:5'],
            'width' => ['nullable', 'integer', 'min:30', 'max:200'],
            'height' => ['nullable', 'integer', 'min:20', 'max:200'],
        ]);
    }

    private function formData(string $type): array
    {
        return [
            'type' => $type,
            'pallets' => Pallet::query()->whereNot('status', 'voided')->latest('received_at')->limit(300)->pluck('code', 'id'),
            'lots' => $this->lotOptions(true),
            'varieties' => $this->varietyOptions(),
            'width' => (int) setting('labels.width', 100),
            'height' => (int) setting('labels.height', 50),
        ];
    }
}
