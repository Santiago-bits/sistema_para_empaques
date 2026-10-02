<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Base de la API v1. Además de la habilidad del token (read, scale:write, sensors:write),
 * cada acción exige el permiso del usuario dueño del token: un token nunca puede más que su usuario.
 */
abstract class ApiController extends Controller
{
    protected function requirePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403, 'El usuario del token no tiene permiso para esta operación.');
    }

    protected function perPageApi(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }

    /** Metadatos de paginación uniformes. */
    protected function paginated($paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
