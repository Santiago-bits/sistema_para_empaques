<?php

namespace App\Http\Middleware;

use App\Support\Legal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El administrador de un galpón acepta los términos y condiciones (una vez por versión) antes de seguir. */
class RequireTermsAcceptance
{
    private const ALLOWED = ['legal.*', 'logout', 'password.change', 'password.change.update', 'api.*', 'heartbeat'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::ALLOWED) && Legal::mustAccept($request->user())) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tenés que aceptar los términos y condiciones antes de continuar.'], 403)
                : redirect()->guest(route('legal.accept'));
        }

        return $next($request);
    }
}
