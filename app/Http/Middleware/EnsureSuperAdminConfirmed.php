<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administración general (/administradorgeneral): sólo el Super Administrador, y volviendo a escribir su
 * contraseña cada CONFIRM_MINUTES minutos (aunque alguien use una sesión abierta en otra PC, no entra).
 * Para cualquier otro usuario la sección no existe (404).
 */
class EnsureSuperAdminConfirmed
{
    public const CONFIRM_MINUTES = 15;

    public const SESSION_KEY = 'superadmin.confirmed_at';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin() && $request->user()->isActive(), 404);

        if (! $request->routeIs('superadmin.confirm*') && ! self::confirmed($request)) {
            return redirect()->guest(route('superadmin.confirm'));
        }

        $response = $next($request);
        // Datos personales: que el navegador no guarde copias de estas páginas.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public static function confirmed(Request $request): bool
    {
        $at = (int) $request->session()->get(self::SESSION_KEY, 0);

        return $at > 0 && $at >= now()->subMinutes(self::CONFIRM_MINUTES)->getTimestamp();
    }
}
