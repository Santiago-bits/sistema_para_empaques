<?php

namespace App\Services\Devices;

use Symfony\Component\HttpFoundation\Response;

/**
 * Imprime desde el navegador: genera una página con CSS @page del tamaño de la
 * etiqueta (por defecto 100 × 50 mm) y el operador usa "Imprimir" del navegador
 * con la impresora de etiquetas configurada en Windows.
 *
 * TODO (ZPL): para impresoras Zebra en modo nativo implementar `ZplLabelPrinter`
 * que arme el comando ZPL (^XA ^FO ^BC ^BQ ^FD ... ^XZ) por etiqueta y lo envíe
 * por TCP al puerto 9100 de la impresora (o lo devuelva como descarga .zpl).
 * Se selecciona con GALPON_LABEL_PRINTER=zpl (config/galpon.php → devices.label_printer).
 */
class BrowserLabelPrinter implements LabelPrinter
{
    public function driver(): string
    {
        return 'browser';
    }

    public function render(array $labels, array $options = []): Response
    {
        $width = max(30, min(200, (int) ($options['width'] ?? 100)));
        $height = max(20, min(200, (int) ($options['height'] ?? 50)));

        return response()->view('labels.print', [
            'labels' => $labels,
            'width' => $width,
            'height' => $height,
            'title' => $options['title'] ?? 'Etiquetas',
            'back' => $options['back'] ?? null,
        ]);
    }
}
