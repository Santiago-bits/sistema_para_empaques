<?php

namespace App\Http\Controllers;

use App\Models\Empaque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lector de códigos QR: la página con la cámara y la búsqueda del empaque leído.
 */
class LectorController extends Controller
{
    /**
     * Página del lector (cámara + ingreso manual del código).
     */
    public function index(): View
    {
        return view('lector');
    }

    /**
     * Recibe el texto leído (URL del QR o código tipeado) y redirige al empaque.
     */
    public function buscar(Request $request): RedirectResponse
    {
        $request->validate(
            ['codigo' => ['required', 'string', 'max:500']],
            ['codigo.required' => 'Ingresá o escaneá un código.'],
        );

        $codigo = Empaque::extraerCodigo($request->query('codigo'));

        if (! $codigo) {
            return to_route('lector')->with('error', 'El código leído no pertenece a este sistema.');
        }

        // withTrashed: un empaque dado de baja también se muestra (con su aviso), no como "no existe".
        if (! Empaque::withTrashed()->where('codigo', $codigo)->exists()) {
            return to_route('lector')->with('error', "No existe ningún empaque con el código {$codigo}.");
        }

        return to_route('empaques.show', $codigo);
    }
}
