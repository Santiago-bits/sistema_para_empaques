<?php

namespace App\Http\Controllers;

use App\Models\Empaque;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Generación del código QR de un empaque: imagen, descarga y etiqueta imprimible.
 * El QR no se guarda: se genera en el momento a partir de la URL del empaque.
 */
class EmpaqueQrController extends Controller
{
    /**
     * Imagen del QR para mostrar dentro de una página (<img src="...">).
     */
    public function imagen(Empaque $empaque, string $formato): Response
    {
        $qr = $this->generar($empaque, $formato);

        return response($qr->getString(), 200, ['Content-Type' => $qr->getMimeType()]);
    }

    /**
     * Descarga el QR como archivo, con el código impreso debajo.
     */
    public function descargar(Empaque $empaque, string $formato): Response
    {
        $qr = $this->generar($empaque, $formato, conTexto: true);
        $archivo = "qr-{$empaque->codigo}.{$formato}";

        return response($qr->getString(), 200, [
            'Content-Type' => $qr->getMimeType(),
            'Content-Disposition' => "attachment; filename=\"{$archivo}\"",
        ]);
    }

    /**
     * Página lista para imprimir: QR + código + nombre.
     */
    public function etiqueta(Empaque $empaque): View
    {
        return view('empaques.etiqueta', ['empaque' => $empaque]);
    }

    private function generar(Empaque $empaque, string $formato, bool $conTexto = false): ResultInterface
    {
        $builder = new Builder(
            writer: $formato === 'svg' ? new SvgWriter : new PngWriter,
            data: $empaque->urlPublica(),
            // Nivel medio: tolera etiquetas algo gastadas o sucias sin agrandar demasiado el QR.
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 400,
            margin: 16,
            labelText: $conTexto ? $empaque->codigo : '',
        );

        return $builder->build();
    }
}
