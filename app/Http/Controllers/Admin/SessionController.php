<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions)
    {
    }

    public function index(): View
    {
        return view('admin.sessions.index', ['sessions' => $this->sessions->active(240)]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        if ($session === $request->session()->getId()) {
            return back()->with('error', 'No podés cerrar tu propia sesión desde aquí. Usá "Cerrar sesión".');
        }
        $this->sessions->terminate($session);

        return back()->with('success', 'Sesión cerrada.');
    }
}
