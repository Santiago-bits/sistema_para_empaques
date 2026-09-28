<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpaque;
use App\Http\Requests\EmpaqueRequest;
use App\Models\Empaque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpaqueController extends Controller
{
    /**
     * Listado con búsqueda por código/nombre y filtro por estado.
     */
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar'));
        $estado = EstadoEmpaque::tryFrom((string) $request->query('estado'));

        $empaques = Empaque::query()
            ->buscar($busqueda)
            ->conEstado($estado)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('empaques.index', [
            'empaques' => $empaques,
            'busqueda' => $busqueda,
            'estadoSeleccionado' => $estado,
            'estados' => EstadoEmpaque::cases(),
        ]);
    }

    /**
     * Formulario de alta.
     */
    public function create(): View
    {
        return view('empaques.create', [
            'empaque' => new Empaque,
            'estados' => EstadoEmpaque::cases(),
        ]);
    }

    /**
     * Guarda un empaque nuevo. El código lo genera el modelo.
     */
    public function store(EmpaqueRequest $request): RedirectResponse
    {
        $empaque = new Empaque($request->validated());
        $empaque->user_id = $request->user()?->id;
        $empaque->save();

        return redirect()
            ->route('empaques.show', $empaque)
            ->with('success', "Empaque {$empaque->codigo} creado correctamente.");
    }

    /**
     * Detalle de un empaque.
     */
    public function show(Empaque $empaque): View
    {
        // La ruta admite empaques eliminados (withTrashed) para poder avisar que fue dado de baja.
        if ($empaque->trashed()) {
            return view('empaques.baja', ['empaque' => $empaque]);
        }

        // Si APP_URL es localhost, el QR no abrirá nada al escanearlo desde otro dispositivo.
        $hostQr = parse_url(config('app.url'), PHP_URL_HOST);

        return view('empaques.show', [
            'empaque' => $empaque,
            'qrSoloLocal' => in_array($hostQr, ['localhost', '127.0.0.1'], true),
        ]);
    }

    /**
     * Formulario de edición.
     */
    public function edit(Empaque $empaque): View
    {
        return view('empaques.edit', [
            'empaque' => $empaque,
            'estados' => EstadoEmpaque::cases(),
        ]);
    }

    /**
     * Actualiza los datos editables (el código nunca cambia).
     */
    public function update(EmpaqueRequest $request, Empaque $empaque): RedirectResponse
    {
        $empaque->update($request->validated());

        return redirect()
            ->route('empaques.show', $empaque)
            ->with('success', 'Empaque actualizado correctamente.');
    }

    /**
     * Da de baja el empaque (soft delete).
     */
    public function destroy(Empaque $empaque): RedirectResponse
    {
        $empaque->delete();

        return redirect()
            ->route('empaques.index')
            ->with('success', "Empaque {$empaque->codigo} eliminado.");
    }
}
