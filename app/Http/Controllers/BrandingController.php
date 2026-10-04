<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo de la empresa (Configuración → Empresa). Se sirve desde acá y no con el enlace public/storage porque
 * Hostinger tiene deshabilitada la función symlink(): sin ese enlace la imagen salía rota.
 */
class BrandingController extends Controller
{
    public function logo(): StreamedResponse
    {
        $path = (string) setting('company.logo', '');
        abort_unless(str_starts_with($path, 'branding/') && Storage::disk('public')->exists($path), 404);

        // La URL lleva ?v=<archivo>: al subir otro logo cambia, así que se puede guardar en caché una semana.
        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** URL del logo actual, o null si no hay. */
    public static function url(): ?string
    {
        $path = (string) setting('company.logo', '');

        return $path === '' ? null : route('branding.logo', ['v' => substr(md5($path), 0, 10)]);
    }
}
