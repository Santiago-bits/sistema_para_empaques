<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloquea el acceso por URL a un módulo desactivado (uso: ->middleware('module:loads')). */
class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleService $modules)
    {
    }

    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            if (! $this->modules->enabled($key)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
