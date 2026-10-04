<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // La caché de LiteSpeed (Hostinger) no debe guardar páginas: cada una lleva su clave de formulario (CSRF).
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        // No anunciar la versión de PHP (la agrega PHP por su cuenta con expose_php).
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        // Sitio publicado con HTTPS: el navegador no vuelve a intentar por HTTP durante un año. Se decide por
        // APP_URL y no por la petición porque detrás de la CDN de Hostinger PHP puede recibirla como HTTP.
        // Sin includeSubDomains: otros subdominios del dominio pueden no tener certificado.
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
