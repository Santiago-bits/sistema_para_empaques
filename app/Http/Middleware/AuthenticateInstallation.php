<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a un empaque ante el Panel General: encabezado X-Installation-Id + «Authorization: Bearer
 * <clave de licencia>». La comparación es en tiempo constante y el error es siempre el mismo.
 */
class AuthenticateInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        $installation = (string) $request->header('X-Installation-Id', '');
        $key = (string) $request->bearerToken();

        $license = $installation !== '' ? License::query()->where('installation_id', $installation)->first() : null;
        $valid = $license !== null && $key !== '' && hash_equals((string) $license->license_key, $key);
        if (! $valid) {
            return response()->json(['message' => 'Instalación o clave de licencia inválida.'], 401);
        }

        $request->attributes->set('license', $license);

        return $next($request);
    }
}
