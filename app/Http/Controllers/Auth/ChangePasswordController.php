<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Cambio obligatorio de la contraseña temporal asignada por un administrador. */
class ChangePasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('home');
        }

        return view('auth.change-password');
    }

    public function update(Request $request, PasswordResetService $resets): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['current_password' => 'contraseña temporal', 'password' => 'contraseña']);

        $resets->changeAfterTemporary($request->user(), $data['password']);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('success', 'Contraseña actualizada. ¡Bienvenido!');
    }
}
