<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API siempre responde JSON (401/403/404/422 incluidos), aunque el dispositivo
 * no envíe «Accept: application/json». Así nunca recibe una redirección al login.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
