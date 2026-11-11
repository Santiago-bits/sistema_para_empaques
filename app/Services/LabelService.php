<?php

namespace App\Services;

use App\Models\Crate;
use App\Models\Pallet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Arma los datos de etiquetas (cajón / pallet) con código de barras Code128 y QR
 * en SVG (sin ext-gd). El render final lo hace un LabelPrinter.
 */
class LabelService
{
    /** Máximo de etiquetas por impresión para no generar páginas gigantes. */
    public const MAX_LABELS = 500;

    public function barcodeSvg(string $code, float $widthFactor = 1.6, int $height = 40): string
    {
        $svg = (new BarcodeGeneratorSVG)->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);

        return $this->stripProlog($svg);
    }

    public function qrSvg(string $text, int $size = 120): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $this->stripProlog($writer->writeString($text));
    }

    public function crateLabel(Crate $crate): array
    {
        $crate->loadMissing('lot', 'variety', 'size', 'grade', 'containerType', 'pallet', 'producer', 'packer', 'currentLoad.destination', 'currentLoad.client');
        $load = $crate->currentLoad;
        // A dónde va: el destino de la carga en la que está el cajón (si ya se cargó).
        $destination = $load ? ($load->destination?->name ?: ($load->client?->business_name
            ?: (\App\Models\Load::COMMERCIAL_DESTINATIONS[$load->commercial_destination] ?? null))) : null;
        $nominal = (float) setting('label.nominal_kg', 0);
        $weight = $crate->weight !== null ? kg($crate->weight) : ($nominal > 0 ? 'aprox. '.kg($nominal, 0) : null);

        return [
            'code' => $crate->code,
            'title' => 'Cajón',
            'lines' => array_values(array_filter([
                ['Variedad', $crate->variety?->name],
                ['Selección', $crate->grade?->name],
                ['Destino', $destination],
                ['Envase', $crate->containerType?->name],
                ['Lote', $crate->lot?->code],
                ['Pallet', $crate->pallet?->code],
                ['Productor', $crate->producer?->name],
                ['Empacador N°', $crate->packer?->code],
            ], fn ($line) => $line[1] !== null && $line[1] !== '')),
            // Recuadro de seguridad (donde antes iba el QR): peso en grande y los datos que no se pueden tocar.
            'security' => [
                'label' => 'Peso neto',
                'value' => $weight ?? '—',
                'details' => array_values(array_filter([
                    $crate->size?->name ? 'Calibre '.$crate->size->name : null,
                    ($date = ($crate->processed_at ?? $crate->created_at)?->format('d/m/Y')) ? 'Empaque '.$date : null,
                ])),
            ],
            'control' => $this->controlCode($crate),
            'barcode_svg' => $this->barcodeSvg($crate->barcode ?: $crate->code),
            'barcode_text' => $crate->barcode ?: $crate->code,
            'qr_svg' => $this->qrSvg($this->qrPayload('CJ', $crate->code)),
        ];
    }

    public function palletLabel(Pallet $pallet): array
    {
        $pallet->loadMissing('lot', 'variety', 'producer', 'owner');

        return [
            'code' => $pallet->code,
            'title' => 'Pallet',
            'lines' => array_values(array_filter([
                ['Lote', $pallet->lot?->code],
                ['Variedad', $pallet->variety?->name],
                ['Productor', $pallet->producer?->name],
                ['Propietario', $pallet->owner?->name],
                ['Ingreso', fdate($pallet->received_at, true)],
            ], fn ($line) => $line[1] !== null && $line[1] !== '')),
            'security' => [
                'label' => 'Capacidad',
                'value' => num($pallet->capacityLimit()).' cajones',
                'details' => array_values(array_filter([
                    $pallet->gross_weight ? 'Bruto '.kg($pallet->gross_weight, 1) : null,
                    $pallet->received_at ? 'Ingreso '.$pallet->received_at->format('d/m/Y') : null,
                ])),
            ],
            'control' => $this->controlCode($pallet),
            'barcode_svg' => $this->barcodeSvg($pallet->barcode ?: $pallet->code),
            'barcode_text' => $pallet->barcode ?: $pallet->code,
            'qr_svg' => $this->qrSvg($this->qrPayload('PAL', $pallet->code)),
        ];
    }

    /**
     * Encabezado de la etiqueta: empaque, CUIT y dirección (Configuración → Etiquetas) y, siempre que esté
     * cargado, el código del galpón en ARCA (Configuración → ARCA).
     */
    public function companyLine(): ?string
    {
        $arca = trim((string) setting('arca.warehouse_code'));
        $parts = array_filter([
            setting('label.show_company', true) ? setting('company.name') : null,
            setting('label.show_company', true) && setting('company.cuit') ? 'CUIT '.setting('company.cuit') : null,
            $arca !== '' ? 'Galpón ARCA N° '.$arca : null,
            setting('label.show_company', true) ? setting('company.address') : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Pie con los datos oficiales del envase: SENASA, registro provincial, RENSPA, norma y origen. */
    public function regulatoryLine(): ?string
    {
        if (! setting('label.show_regulatory', true)) {
            return null;
        }
        $senasa = trim((string) setting('label.senasa_number'));
        $parts = array_filter([
            setting('label.origin_legend'),
            $senasa !== '' ? 'SENASA '.(preg_match('/^[A-Za-z]-/', $senasa) ? $senasa : 'E-'.$senasa) : null,
            setting('label.provincial_registry') ? 'Reg. Prov. Empaque N° '.setting('label.provincial_registry') : null,
            setting('label.renspa') ? 'RENSPA '.setting('label.renspa') : null,
            setting('label.decree'),
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * Código de control de la etiqueta (4 caracteres): sale del código, el peso (o los cajones) y la fecha, firmado
     * con la clave del sistema. Si alguien cambia a mano el peso impreso, el control ya no coincide con el que
     * muestra el sistema en la ficha del cajón o del pallet: la etiqueta queda en evidencia.
     */
    public function controlCode(Crate|Pallet $item): string
    {
        $data = $item instanceof Crate
            ? 'CJ|'.$item->code.'|'.($item->weight !== null ? number_format((float) $item->weight, 2, '.', '') : '-').'|'.($item->processed_at ?? $item->created_at)?->format('Ymd')
            : 'PAL|'.$item->code.'|'.$item->capacityLimit().'|'.$item->received_at?->format('Ymd');
        $hash = hash_hmac('sha256', $data, (string) config('app.key'));
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // sin 0/O ni 1/I: no se confunden al leerlos
        $n = hexdec(substr($hash, 0, 8));
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $alphabet[$n % 32];
            $n = intdiv($n, 32);
        }

        return $out;
    }

    /** Datos legales que faltan cargar para la etiqueta (para avisar en la pantalla de imprimir). */
    public function missingRegulatory(): array
    {
        if (! setting('label.show_regulatory', true)) {
            return ['off'];
        }

        return array_keys(array_filter([
            'SENASA' => trim((string) setting('label.senasa_number')) === '',
            'Registro provincial' => trim((string) setting('label.provincial_registry')) === '',
            'RENSPA' => trim((string) setting('label.renspa')) === '',
        ]));
    }

    /** El QR lleva la URL de trazabilidad: escaneado con un celular abre la ficha (requiere login). */
    private function qrPayload(string $type, string $code): string
    {
        return route('traceability.index', ['code' => $code]);
    }

    private function stripProlog(string $svg): string
    {
        $svg = preg_replace('/<\?xml.*?\?>/s', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/s', '', $svg) ?? $svg;

        return trim($svg);
    }
}
