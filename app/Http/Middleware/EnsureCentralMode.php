<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El Panel General sólo existe en el servidor del proveedor (GALPON_CENTRAL_MODE=true). */
class EnsureCentralMode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('galpon.central.mode'), 404);

        return $next($request);
    }
}
