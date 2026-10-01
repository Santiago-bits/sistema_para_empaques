<?php

namespace App\Services\Devices;

use Symfony\Component\HttpFoundation\Response;

/**
 * Impresión de etiquetas. Cada etiqueta es un array con:
 * code, title, lines (lista de [etiqueta, valor]), barcode_svg, qr_svg.
 */
interface LabelPrinter
{
    public function driver(): string;

    /**
     * @param  list<array<string, mixed>>  $labels
     * @param  array{width?: int, height?: int, title?: string, back?: ?string}  $options
     */
    public function render(array $labels, array $options = []): Response;
}
