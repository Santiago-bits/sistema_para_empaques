<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

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
}
