<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Notifications\PasswordResetRequested;
use App\Support\ErrorReporter;
use App\Support\LoginIdentifiers;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Recuperación de contraseña en dos caminos:
 *  - Usuario con email: código de 6 dígitos por email → se valida en el sistema → contraseña nueva.
 *    El código se guarda con hash (nunca en texto plano), vence en CODE_MINUTES y admite CODE_ATTEMPTS intentos.
 *  - Usuario sin email (lo habitual en planta): aviso a los administradores, que le asignan
 *    una contraseña temporal; el sistema obliga a cambiarla en el siguiente ingreso.
 * La respuesta al público es siempre la misma: no revela si el usuario existe.
 */
class PasswordResetService
{
    /** Minutos de validez del código enviado por email. */
    public const CODE_MINUTES = 15;

    /** Intentos para escribir el código; al agotarlos hay que pedir uno nuevo. */
    public const CODE_ATTEMPTS = 5;

    /** Minutos entre avisos repetidos a los administradores por el mismo usuario. */
    private const ADMIN_NOTICE_COOLDOWN = 15;

    public function __construct(
        private readonly AuditService $audit,
        private readonly SessionService $sessions,
    ) {
    }

    /**
     * Pedido de recuperación. Devuelve el id del usuario al que se le envió el código (el controlador lo
     * guarda en la sesión del servidor para el paso siguiente) o null si no corresponde enviarlo.
     */
    public function request(string $login, ?string $ip): ?int
    {
        $user = LoginIdentifiers::find($login);
        if (! $user || ! $user->isActive()) {
            return null;
        }

        $channel = $user->email && $this->sendCode($user) ? 'email' : 'admin';
        if ($channel === 'admin') {
            $this->notifyAdmins($user, $ip);
        }

        $this->audit->log('password_reset_requested', $user, null, ['channel' => $channel, 'ip' => $ip],
            $channel === 'email' ? 'Pidió recuperar la contraseña (código por email)' : 'Pidió recuperar la contraseña (aviso al administrador)');

        return $channel === 'email' ? $user->id : null;
    }

    /**
     * Valida el código del email. Es de un solo uso: se borra al acertar, al vencer o al agotar los intentos.
     *
     * @throws BusinessException si venció o se agotaron los intentos (hay que pedir uno nuevo)
     */
    public function verifyCode(int $userId, #[\SensitiveParameter] string $code): bool
    {
        $user = User::query()->find($userId);
        if (! $user?->email || ! $user->isActive()) {
            return false;
        }

        $row = $this->codes()->where('email', $user->email)->first();
        if (! $row || Carbon::parse($row->created_at)->addMinutes(self::CODE_MINUTES)->isPast()) {
            $this->codes()->where('email', $user->email)->delete();
            throw new BusinessException('El código venció. Pedí uno nuevo con «Reenviar código».');
        }

        $attemptsKey = $this->attemptsKey($user);
        if (! Hash::check($code, $row->token)) {
            RateLimiter::hit($attemptsKey, self::CODE_MINUTES * 60);
            if (RateLimiter::attempts($attemptsKey) >= self::CODE_ATTEMPTS) {
                $this->codes()->where('email', $user->email)->delete();
                RateLimiter::clear($attemptsKey);
                throw new BusinessException('Te equivocaste demasiadas veces. Pedí un código nuevo con «Reenviar código».');
            }

            return false;
        }

        RateLimiter::clear($attemptsKey);
        $this->codes()->where('email', $user->email)->delete();

        return true;
    }

    /** Contraseña nueva después de validar el código. Cierra las sesiones abiertas en otros equipos. */
    public function resetAfterCode(User $user, #[\SensitiveParameter] string $password): void
    {
        if (! $user->isActive()) {
            throw new BusinessException('Tu usuario está inactivo. Contactá al administrador.');
        }

        $this->storePassword($user, $password, mustChange: false);
        $this->audit->log('password_reset', $user, description: 'Restableció su contraseña con el código del email');
    }

    /** El administrador asigna una contraseña temporal. Devuelve la contraseña para mostrarla UNA vez. */
    public function assignTemporary(User $user, User $by): string
    {
        if ($user->is($by)) {
            throw new BusinessException('Para cambiar tu propia contraseña usá «Mi perfil».');
        }

        $temporary = self::generateTemporary();
        $this->storePassword($user, $temporary, mustChange: true);
        Cache::forget($this->noticeKey($user));
        $this->audit->log('password_reset_admin', $user, description: 'Asignó una contraseña temporal a '.$user->username);

        return $temporary;
    }

    /** Cambio obligatorio después de una contraseña temporal. */
    public function changeAfterTemporary(User $user, #[\SensitiveParameter] string $password): void
    {
        if (Hash::check($password, $user->password)) {
            throw new BusinessException('La contraseña nueva tiene que ser distinta de la temporal.');
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();
            $this->audit->log('password_change', $user, description: 'Reemplazó la contraseña temporal');
        });
    }

    /** 10 caracteres sin ambiguos (0/O, 1/l/I), agrupados para dictarlos: «Kq7m-Xp3a-Tz». */
    public static function generateTemporary(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (! preg_match('/[a-z]/', $raw) || ! preg_match('/[A-Z]/', $raw) || ! preg_match('/\d/', $raw));

        return implode('-', str_split($raw, 4));
    }

    private function storePassword(User $user, string $password, bool $mustChange): void
    {
        DB::transaction(function () use ($user, $password, $mustChange) {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => $mustChange,
                'password_changed_at' => $mustChange ? null : now(),
            ])->save();
            // Cierra sesiones abiertas, tokens de API y "recordarme": quien tuviera la contraseña vieja queda afuera.
            $this->sessions->terminateAllFor($user);
            DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->delete();
        });
    }

    /** Genera un código nuevo (reemplaza al anterior), lo guarda con hash y lo envía por email. */
    private function sendCode(User $user): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->codes()->updateOrInsert(['email' => $user->email], ['token' => Hash::make($code), 'created_at' => now()]);
            RateLimiter::clear($this->attemptsKey($user));
            $user->notify(new PasswordResetCode($code));

            return true;
        } catch (Throwable $e) {
            // Correo mal configurado: se registra el error y se sigue por el camino del administrador.
            ErrorReporter::capture($e, 'mail');
            $this->codes()->where('email', $user->email)->delete();

            return false;
        }
    }

    private function codes(): Builder
    {
        return DB::table(config('auth.passwords.users.table'));
    }

    private function attemptsKey(User $user): string
    {
        return 'password-reset-code:'.$user->id;
    }

    private function notifyAdmins(User $user, ?string $ip): void
    {
        if (! Cache::add($this->noticeKey($user), true, now()->addMinutes(self::ADMIN_NOTICE_COOLDOWN))) {
            return;
        }

        $admins = User::query()->where('status', 'active')->whereKeyNot($user->id)->with('role')->get()
            ->filter(fn (User $admin) => $admin->can('update', $user));

        Notification::send($admins, new PasswordResetRequested($user, $ip));
    }

    private function noticeKey(User $user): string
    {
        return 'password-reset-notice:'.$user->id;
    }
}
