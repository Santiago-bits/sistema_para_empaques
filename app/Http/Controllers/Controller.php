<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Los controladores deben ser pequeños: validan (Form Requests), autorizan
 * (Policies / permisos) y delegan la lógica de negocio a los Services.
 */
abstract class Controller
{
    use AuthorizesRequests;

    /** Registros por página permitidos: 10, 25, 50 o 100 (nunca miles de golpe). */
    protected function perPage(Request $request, int $default = 25): int
    {
        $value = $request->integer('per_page', $default);

        return in_array($value, [10, 25, 50, 100], true) ? $value : $default;
    }

    /**
     * Fecha de un filtro (?from=2026-10-01). Si viene vacía o con cualquier otra cosa (texto inventado, fechas
     * imposibles) se ignora en vez de romper la pantalla con un error 500.
     */
    protected static function dateParam(Request $request, string $key): ?Carbon
    {
        $value = $request->input($key);
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/', trim($value))) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', substr(trim($value), 0, 10)));
        if (! checkdate($m, $d, $y) || $y < 1900 || $y > 2999) {
            return null;
        }
        try {
            return Carbon::parse(trim($value));
        } catch (\Throwable) {
            return null;
        }
    }
}
