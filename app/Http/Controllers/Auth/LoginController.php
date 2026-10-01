<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
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
        return view('auth.login', ['identifiers' => $this->identifiers()]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = trim($request->string('login'));
        $user = $this->findUser($login);

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

    private function identifiers(): array
    {
        $allowed = ['username', 'dni', 'cuit', 'internal_code', 'email'];

        return array_values(array_intersect($allowed, (array) setting('login.identifiers', ['username'])));
    }

    private function findUser(string $login): ?User
    {
        $identifiers = $this->identifiers() ?: ['username'];
        $normalized = preg_replace('/[\s.\-]/', '', $login);

        return User::query()
            ->where(function ($query) use ($identifiers, $login, $normalized) {
                foreach ($identifiers as $column) {
                    $value = in_array($column, ['dni', 'cuit'], true) ? $normalized : $login;
                    $query->orWhere($column, $value);
                }
            })
            ->with('role')
            ->first();
    }
}
