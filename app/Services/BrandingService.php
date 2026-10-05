<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Logo de la empresa (Configuración → Empresa) e íconos que salen de él: pestaña del navegador, app instalada
 * (escritorio y celular) y acceso directo del iPhone. Los íconos se generan con GD la primera vez que se piden y
 * se guardan en una carpeta por versión del logo: al subir otro logo cambia la versión, la URL y el ícono.
 * Sin logo se usan los íconos de siempre (public/icons).
 */
class BrandingService
{
    /** nombre => [lado en px, fondo blanco (sin transparencia), proporción del lado que ocupa el logo]. */
    public const ICONS = [
        'favicon-32' => [32, false, 1.0],
        'apple-touch-icon' => [180, true, 0.86], // iOS no admite transparencia
        'icon-192' => [192, false, 1.0],
        'icon-512' => [512, false, 1.0],
        'icon-maskable-512' => [512, true, 0.72], // Android recorta los bordes (zona segura del 80 %)
    ];

    private const DIRECTORY = 'branding/icons';

    public function logoPath(): ?string
    {
        $path = (string) setting('company.logo', '');

        return str_starts_with($path, 'branding/') && Storage::disk('public')->exists($path) ? $path : null;
    }

    /** Cambia con cada logo nuevo: va en las URL para que el navegador no se quede con el anterior. */
    public function version(): ?string
    {
        $path = (string) setting('company.logo', '');

        return $path === '' ? null : substr(md5($path), 0, 10);
    }

    public function logoUrl(): ?string
    {
        $version = $this->version();

        return $version ? route('branding.logo', ['v' => $version]) : null;
    }

    /** URL del ícono: el generado desde el logo o, sin logo, el de siempre. */
    public function iconUrl(string $name): string
    {
        $version = $this->version();

        return $version
            ? route('branding.icon', ['name' => $name, 'v' => $version])
            : asset('icons/'.$name.'.png').'?v=2';
    }

    /**
     * Ruta (en el disco public) del ícono generado desde el logo actual; lo genera si todavía no existe.
     * null si no hay logo o la imagen no se puede leer (se usa el ícono de siempre).
     */
    public function icon(string $name): ?string
    {
        if (! isset(self::ICONS[$name]) || ! ($logo = $this->logoPath())) {
            return null;
        }

        $disk = Storage::disk('public');
        $folder = self::DIRECTORY.'/'.$this->version();
        $target = $folder.'/'.$name.'.png';
        if ($disk->exists($target)) {
            return $target;
        }

        // Sin la extensión GD de PHP no se puede generar: se usa el ícono de siempre (en vez de un error 500).
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring((string) $disk->get($logo));
        if (! $source instanceof GdImage) {
            return null;
        }

        [$size, $opaque, $share] = self::ICONS[$name];
        $png = $this->render($source, $size, $opaque, $share);
        imagedestroy($source);

        // Íconos de logos anteriores: ya no se usan.
        foreach ($disk->directories(self::DIRECTORY) as $old) {
            if ($old !== $folder) {
                $disk->deleteDirectory($old);
            }
        }
        $disk->put($target, $png);

        return $target;
    }

    /** Logo centrado en un cuadrado de $size px, sin deformarlo. */
    private function render(GdImage $source, int $size, bool $opaque, float $share): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $background = $opaque ? imagecolorallocate($canvas, 255, 255, 255) : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
        imagealphablending($canvas, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $box = $size * $share;
        $scale = min($box / $width, $box / $height);
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));
        imagecopyresampled($canvas, $source, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, $width, $height);

        ob_start();
        imagepng($canvas, null, 9);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }
}
