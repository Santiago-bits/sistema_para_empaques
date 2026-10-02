<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use App\Support\LoginIdentifiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login por identificador configurable: usuario, DNI, CUIT, código interno o email
 * (Configuración → Seguridad). El email no es obligatorio.
 */
class LoginController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function show(): View
    {
        return view('auth.login', ['hint' => LoginIdentifiers::hint()]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = trim($request->string('login'));
        $user = LoginIdentifiers::find($login);

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            $this->audit->log('login_failed', null, null, ['login' => $login], 'Intento de acceso fallido');

            throw ValidationException::withMessages(['login' => 'Las credenciales no son correctas.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'Tu usuario está inactivo. Contactá al administrador.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->log('login', $user, description: 'Inicio de sesión');

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            $this->audit->log('logout', $user, description: 'Cierre de sesión');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
