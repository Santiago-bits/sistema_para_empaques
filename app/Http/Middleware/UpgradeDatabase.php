<?php

namespace App\Http\Middleware;

use App\Services\DatabaseUpgrader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Después de cada actualización del sistema, la primera visita pone la base de datos al día (ver DatabaseUpgrader).
 * En el uso normal sólo compara una marca en disco: no consulta la base.
 */
class UpgradeDatabase
{
    public function __construct(private readonly DatabaseUpgrader $upgrader)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('install.*', 'app.manifest') || $request->is('up') || $this->upgrader->isUpToDate()) {
            return $next($request);
        }

        try {
            if (! $this->upgrader->isInstalled()) {
                return $next($request); // instalación nueva: la base la arma el instalador
            }
        } catch (Throwable) {
            return $next($request); // sin conexión a la base: lo informa el instalador o la página de error
        }
        if ($this->upgrader->failedRecently()) {
            return $next($request); // el super admin ve el aviso y puede reintentar
        }

        if ($this->upgrader->run() === 'busy') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'El sistema se está actualizando. Probá de nuevo en unos segundos.'], 503, ['Retry-After' => '5']);
            }

            return response()->view('errors.upgrading', [], 503, ['Retry-After' => '5']);
        }

        return $next($request);
    }
}
