<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * «Ingresar con Google»: sólo entra quien YA es usuario del sistema con ese mismo email (verificado por Google).
 * No crea usuarios: el alta la sigue haciendo el administrador desde Usuarios.
 */
class GoogleLoginController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /** El botón sólo se muestra si las credenciales de Google están en el .env. */
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        if (! self::enabled()) {
            return redirect()->route('login')->with('error', 'El ingreso con Google no está habilitado en este sistema.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! self::enabled()) {
            return redirect()->route('login')->with('error', 'El ingreso con Google no está habilitado en este sistema.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            // Canceló en Google, el enlace venció o se abrió dos veces: se vuelve a empezar.
            return redirect()->route('login')->with('error', 'No se pudo ingresar con Google. Intentá de nuevo.');
        }

        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = (bool) data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false));
        if ($email === '' || ! $verified) {
            return redirect()->route('login')->with('error', 'Tu cuenta de Google no tiene un email verificado.');
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->with('role')->first();
        if (! $user) {
            $this->audit->log('login_failed', null, null, ['login' => 'google:'.$email], 'Intento de acceso con Google sin usuario asociado');

            return redirect()->route('login')->with('error', "No hay ningún usuario del sistema con el email {$email}. Pedile al administrador que lo cargue en tu ficha de usuario.");
        }

        if (! $user->isActive()) {
            return redirect()->route('login')->with('error', 'Tu usuario está inactivo. Contactá al administrador.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->log('login', $user, description: 'Inicio de sesión con Google');

        return redirect()->intended(route('home'));
    }
}
