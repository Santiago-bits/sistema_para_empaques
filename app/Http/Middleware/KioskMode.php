<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuarios en modo kiosco sólo acceden a la pantalla de escaneo (y a lo que
 * ella necesita). Reduce errores en las PCs dedicadas a producción.
 */
class KioskMode
{
    private const ALLOWED = ['production.scan', 'production.scan.*', 'kiosk', 'logout', 'api.*', 'notifications.*', 'heartbeat'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->kiosk_mode && ! $request->routeIs(...self::ALLOWED)) {
            return redirect()->route('kiosk');
        }

        return $next($request);
    }
}
