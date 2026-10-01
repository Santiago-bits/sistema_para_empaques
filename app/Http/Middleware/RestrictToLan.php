<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe un módulo a la red local si así está configurado
 * (Configuración → Seguridad → módulos sólo LAN). Uso: ->middleware('lan:production').
 */
class RestrictToLan
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $restricted = (array) setting('network.lan_only_modules', []);

        if (in_array($module, $restricted, true)) {
            $ranges = (array) setting('network.allowed_ranges', []);
            if (! IpUtils::checkIp((string) $request->ip(), $ranges)) {
                abort(403, 'Este módulo sólo puede utilizarse desde las computadoras del galpón.');
            }
        }

        return $next($request);
    }
}
