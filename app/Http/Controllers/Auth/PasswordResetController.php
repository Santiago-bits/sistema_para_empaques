<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use App\Support\LoginIdentifiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** «¿Olvidaste tu contraseña?»: pedido de recuperación y restablecimiento con el enlace del email. */
class PasswordResetController extends Controller
{
    /** Mensaje único para cualquier caso: no revela si el usuario existe ni si tiene email. */
    public const GENERIC_STATUS = 'Si los datos corresponden a un usuario activo, te enviamos un enlace a tu email registrado. Si no tenés email cargado, le avisamos al administrador para que te asigne una contraseña temporal.';

    public function __construct(private readonly PasswordResetService $resets)
    {
    }

    public function create(): View
    {
        return view('auth.forgot-password', [
            'label' => LoginIdentifiers::label(),
            'google' => GoogleLoginController::enabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190']], [], ['login' => 'usuario']);
        $this->resets->request($data['login'], $request->ip());

        return redirect()->route('login')->with('status', self::GENERIC_STATUS);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['password' => 'contraseña']);

        $status = $this->resets->resetWithToken($data['email'], $data['token'], $data['password']);

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === Password::RESET_THROTTLED
                    ? 'Esperá unos minutos antes de volver a intentar.'
                    : 'El enlace no es válido o ya venció. Pedí uno nuevo desde «¿Olvidaste tu contraseña?».',
            ]);
        }

        return redirect()->route('login')->with('status', 'Listo, tu contraseña se actualizó. Ya podés ingresar.');
    }
}
