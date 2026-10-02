<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Support\ErrorReporter;
use App\Support\LoginIdentifiers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Recuperación de contraseña en dos caminos:
 *  - Usuario con email: enlace firmado de un solo uso (broker de Laravel, vence en 60 min).
 *  - Usuario sin email (lo habitual en planta): aviso a los administradores, que le asignan
 *    una contraseña temporal; el sistema obliga a cambiarla en el siguiente ingreso.
 * La respuesta al público es siempre la misma: no revela si el usuario existe.
 */
class PasswordResetService
{
    /** Minutos entre avisos repetidos a los administradores por el mismo usuario. */
    private const ADMIN_NOTICE_COOLDOWN = 15;

    public function __construct(
        private readonly AuditService $audit,
        private readonly SessionService $sessions,
    ) {
    }

    public function request(string $login, ?string $ip): void
    {
        $user = LoginIdentifiers::find($login);
        if (! $user || ! $user->isActive()) {
            return;
        }

        $channel = $user->email && $this->sendLink($user) ? 'email' : 'admin';
        if ($channel === 'admin') {
            $this->notifyAdmins($user, $ip);
        }

        $this->audit->log('password_reset_requested', $user, null, ['channel' => $channel, 'ip' => $ip],
            $channel === 'email' ? 'Pidió recuperar la contraseña (enlace por email)' : 'Pidió recuperar la contraseña (aviso al administrador)');
    }

    /**
     * Restablece con el enlace del email. Devuelve el estado del broker
     * (Password::PASSWORD_RESET si salió bien).
     */
    public function resetWithToken(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): string
    {
        return Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) {
                if (! $user->isActive()) {
                    throw new BusinessException('Tu usuario está inactivo. Contactá al administrador.');
                }
                $this->storePassword($user, $password, mustChange: false);
                $this->audit->log('password_reset', $user, description: 'Restableció su contraseña con el enlace del email');
            },
        );
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

    private function sendLink(User $user): bool
    {
        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email]);

            // RESET_THROTTLED: ya se envió uno hace instantes; para el usuario es igual de válido.
            return in_array($status, [Password::RESET_LINK_SENT, Password::RESET_THROTTLED], true);
        } catch (Throwable $e) {
            // Correo mal configurado: se registra el error y se sigue por el camino del administrador.
            ErrorReporter::capture($e, 'mail');

            return false;
        }
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
