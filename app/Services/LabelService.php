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
        $crate->loadMissing('lot', 'variety', 'size', 'pallet', 'producer');

        return [
            'code' => $crate->code,
            'title' => 'Cajón',
            'lines' => array_values(array_filter([
                ['Lote', $crate->lot?->code],
                ['Variedad', $crate->variety?->name],
                ['Tamaño', $crate->size?->name],
                ['Peso', $crate->weight !== null ? kg($crate->weight) : null],
                ['Pallet', $crate->pallet?->code],
                ['Productor', $crate->producer?->name],
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
