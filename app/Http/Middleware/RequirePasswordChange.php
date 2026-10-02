<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con una contraseña temporal (asignada por un administrador) el usuario sólo puede
 * cambiarla o salir. Va antes que «kiosk» para que también aplique en las PCs de producción.
 */
class RequirePasswordChange
{
    private const ALLOWED = ['password.change', 'password.change.update', 'logout', 'api.*', 'heartbeat'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tenés que cambiar tu contraseña temporal antes de continuar.'], 403)
                : redirect()->route('password.change');
        }

        return $next($request);
    }
}
