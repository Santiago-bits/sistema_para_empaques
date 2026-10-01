<?php

namespace App\Services\Remitos;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Genera códigos QR en SVG (sin ext-gd ni imagick).
 * dompdf renderiza correctamente el SVG incrustado como data URI (verificado en tests).
 */
class QrCodeRenderer
{
    public function svg(string $content, int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($content);
    }

    /** SVG listo para <img src="..."> (pantalla o PDF). */
    public function dataUri(string $content, int $size = 160): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($content, $size));
    }

    /** SVG inline sin la declaración XML (para incrustar en HTML). */
    public function inline(string $content, int $size = 160): string
    {
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $this->svg($content, $size));
    }
}
