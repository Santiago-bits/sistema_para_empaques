<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * El dueño del sistema se olvidó el usuario o la contraseña del Super Administrador y no tiene consola (hosting
 * compartido). Prueba que es el dueño con la contraseña de la base de datos (sólo la conoce quien administra
 * el servidor, y con ella ya tendría acceso a todos los datos): se le muestra su usuario y elige una contraseña
 * nueva. Pocos intentos por minuto y todo queda auditado.
 */
class OwnerRecoveryController extends Controller
{
    public function show(): View
    {
        return view('auth.owner-recovery');
    }

    public function store(Request $request, AuditService $audit, SessionService $sessions): RedirectResponse
    {
        $data = $request->validate([
            'db_password' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['password' => 'contraseña nueva']);

        $expected = (string) config('database.connections.'.config('database.default').'.password');
        if (! hash_equals($expected, (string) ($data['db_password'] ?? ''))) {
            Log::warning('Recuperación de acceso del dueño: contraseña de la base incorrecta', ['ip' => $request->ip()]);

            return back()->withErrors(['db_password' => 'La contraseña de la base de datos no es correcta.']);
        }

        $owner = User::query()->where('status', UserStatus::Active->value)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->orderBy('id')->first();
        if (! $owner) {
            return back()->withErrors(['db_password' => 'No hay ningún Super Administrador activo.']);
        }

        $owner->forceFill(['password' => $data['password'], 'must_change_password' => false, 'password_changed_at' => now()])->save();
        $sessions->terminateAllFor($owner);
        $audit->log('password_reset', $owner, description: 'Recuperó el acceso del Super Administrador con la contraseña de la base (IP '.$request->ip().')');

        return redirect()->route('login')->withInput(['login' => $owner->username])
            ->with('success', 'Listo. Tu usuario de Super Administrador es «'.$owner->username.'». Ingresá con la contraseña nueva.');
    }
}
