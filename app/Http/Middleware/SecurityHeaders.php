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

        // Política de contenido: la página sólo carga scripts, estilos, imágenes y conexiones del propio sistema
        // (no hay CDN ni fuentes externas). Frena la inyección de scripts de otros sitios, los <object>/<embed>,
        // el cambio de <base> y los formularios que mandan datos afuera. Alpine necesita 'unsafe-eval' y las
        // pantallas tienen scripts y estilos propios en línea. Con Vite en desarrollo (npm run dev) no se aplica.
        if (! $response->headers->has('Content-Security-Policy') && ! \Illuminate\Support\Facades\Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "media-src 'self' blob:",
                "worker-src 'self' blob:",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                // Con el sitio en HTTPS, cualquier dirección http:// que quede se pide por https://.
                ...(str_starts_with((string) config('app.url'), 'https://') ? ['upgrade-insecure-requests'] : []),
            ]));
        }
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

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
