<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo de la empresa (Configuración → Empresa) y los íconos que salen de él. Se sirven desde acá y no con el
 * enlace public/storage porque Hostinger tiene deshabilitada la función symlink(): sin ese enlace no se veían.
 */
class BrandingController extends Controller
{
    /** La URL lleva ?v=<versión del logo>: al subir otro cambia, así que se puede guardar en caché una semana. */
    private const HEADERS = ['Cache-Control' => 'public, max-age=604800', 'X-Content-Type-Options' => 'nosniff'];

    public function logo(BrandingService $branding): StreamedResponse
    {
        $path = $branding->logoPath();
        abort_unless($path, 404);

        return Storage::disk('public')->response($path, null, self::HEADERS);
    }

    /** Ícono del navegador / de la app hecho con el logo; sin logo, el ícono de siempre. */
    public function icon(BrandingService $branding, string $name): StreamedResponse|RedirectResponse
    {
        abort_unless(array_key_exists($name, BrandingService::ICONS), 404);
        $path = $branding->icon($name);

        return $path
            ? Storage::disk('public')->response($path, $name.'.png', self::HEADERS + ['Content-Type' => 'image/png'])
            : redirect()->to(asset('icons/'.$name.'.png').'?v=2');
    }

    /** URL del logo actual, o null si no hay. */
    public static function url(): ?string
    {
        return app(BrandingService::class)->logoUrl();
    }
}
