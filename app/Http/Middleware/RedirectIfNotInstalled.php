<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** Si el sistema todavía no tiene usuarios, envía al asistente de instalación. */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('install.*', 'app.manifest') || $request->is('up')) {
            return $next($request);
        }

        try {
            $installed = Schema::hasTable('users') && User::query()->exists();
        } catch (\Throwable) {
            $installed = false;
        }

        if (! $installed) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
