<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Support\LoginIdentifiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * «¿Olvidaste tu contraseña?» en tres pasos: 1) pedir el código, 2) escribir el código del email,
 * 3) crear la contraseña nueva. El avance se guarda en la sesión del servidor: no se puede saltar un paso.
 */
class PasswordResetController extends Controller
{
    /** Mensaje único para cualquier caso: no revela si el usuario existe ni si tiene email. */
    public const GENERIC_STATUS = 'Si los datos corresponden a un usuario con email registrado, te enviamos un código de 6 números. Si no tenés email cargado, le avisamos al administrador para que te asigne una contraseña temporal.';

    /** Sesión del paso 2: a quién se le envió el código (null si nadie: igual se muestra la pantalla). */
    private const FLOW = 'password_reset';

    /** Sesión del paso 3: usuario con el código ya validado y hasta cuándo puede crear la contraseña. */
    private const VERIFIED = 'password_reset_verified';

    private const VERIFIED_MINUTES = 10;

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
        $userId = $this->resets->request($data['login'], $request->ip());

        $request->session()->forget(self::VERIFIED);
        $request->session()->put(self::FLOW, ['user_id' => $userId, 'login' => $data['login']]);

        return redirect()->route('password.code')->with('status', self::GENERIC_STATUS);
    }

    public function code(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::FLOW)) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-code', [
            'login' => (string) $request->session()->get(self::FLOW.'.login'),
            'minutes' => PasswordResetService::CODE_MINUTES,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']], [], ['code' => 'código']);

        $flow = $request->session()->get(self::FLOW);
        if (! $flow) {
            return redirect()->route('password.request')->with('error', 'Pedí un código nuevo para continuar.');
        }

        try {
            $valid = $flow['user_id'] && $this->resets->verifyCode((int) $flow['user_id'], $data['code']);
        } catch (BusinessException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'El código no es correcto. Revisá el email e intentá de nuevo.']);
        }

        $request->session()->forget(self::FLOW);
        $request->session()->put(self::VERIFIED, [
            'user_id' => (int) $flow['user_id'],
            'until' => now()->addMinutes(self::VERIFIED_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('password.reset');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (! $this->verifiedUserId($request)) {
            return redirect()->route('password.request')->with('error', 'Pedí un código nuevo para crear tu contraseña.');
        }

        return view('auth.reset-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $userId = $this->verifiedUserId($request);
        if (! $userId) {
            return redirect()->route('password.request')->with('error', 'Pasó demasiado tiempo. Pedí un código nuevo para crear tu contraseña.');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['password' => 'contraseña']);

        $user = User::query()->find($userId);
        if (! $user) {
            return redirect()->route('password.request');
        }

        try {
            $this->resets->resetAfterCode($user, $data['password']);
        } catch (BusinessException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }

        $request->session()->forget(self::VERIFIED);

        return redirect()->route('login')->with('status', 'Listo, tu contraseña se actualizó. Ya podés ingresar.');
    }

    private function verifiedUserId(Request $request): ?int
    {
        $verified = $request->session()->get(self::VERIFIED);

        return $verified && $verified['until'] >= now()->getTimestamp() ? (int) $verified['user_id'] : null;
    }
}
