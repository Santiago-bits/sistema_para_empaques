<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Cierra la sesión de un usuario dado de baja o suspendido aunque tenga sesión abierta. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            return $this->kickOut($request, 'Usuario inactivo.', 'Tu usuario está inactivo. Contactá al administrador.');
        }
        // Multigalpón: si el galpón del usuario está suspendido, nadie de ese galpón entra.
        if ($user && $user->warehouse_id && \App\Support\Tenancy::ready()
            && ! \App\Models\Warehouse::query()->whereKey($user->warehouse_id)->where('active', true)->exists()) {
            return $this->kickOut($request, 'Galpón suspendido.', 'Tu galpón está suspendido. Contactá al administrador del sistema.');
        }

        return $next($request);
    }

    private function kickOut(Request $request, string $short, string $message): Response
    {
        if ($request->expectsJson()) {
            abort(403, $short);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
