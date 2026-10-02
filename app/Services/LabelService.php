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
        $crate->loadMissing('lot', 'variety', 'size', 'grade', 'containerType', 'pallet', 'producer', 'packer');
        $nominal = (float) setting('label.nominal_kg', 0);
        $weight = $crate->weight !== null ? kg($crate->weight) : ($nominal > 0 ? 'aprox. '.kg($nominal, 0) : null);

        return [
            'code' => $crate->code,
            'title' => 'Cajón',
            'lines' => array_values(array_filter([
                ['Variedad', $crate->variety?->name],
                ['Calibre', $crate->size?->name],
                ['Selección', $crate->grade?->name],
                ['Kg', $weight],
                ['Envase', $crate->containerType?->name],
                ['Lote', $crate->lot?->code],
                ['Pallet', $crate->pallet?->code],
                ['Productor', $crate->producer?->name],
                ['Empacador N°', $crate->packer?->code],
                ['Empaque', ($crate->processed_at ?? $crate->created_at)?->format('d/m/Y')],
            ], fn ($line) => $line[1] !== null && $line[1] !== '')),
            'barcode_svg' => $this->barcodeSvg($crate->barcode ?: $crate->code),
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
                ['Cantidad', $pallet->quantity ? num($pallet->quantity) : null],
            ], fn ($line) => $line[1] !== null && $line[1] !== '')),
            'barcode_svg' => $this->barcodeSvg($pallet->barcode ?: $pallet->code),
            'qr_svg' => $this->qrSvg($this->qrPayload('PAL', $pallet->code)),
        ];
    }

    /** Encabezado de la etiqueta: empaque, CUIT y dirección (Configuración → Etiquetas). */
    public function companyLine(): ?string
    {
        if (! setting('label.show_company', true)) {
            return null;
        }
        $parts = array_filter([
            setting('company.name'),
            setting('company.cuit') ? 'CUIT '.setting('company.cuit') : null,
            setting('company.address'),
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    /** Pie con los datos oficiales del envase: SENASA, registro provincial, RENSPA, norma y origen. */
    public function regulatoryLine(): ?string
    {
        if (! setting('label.show_regulatory', false)) {
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
