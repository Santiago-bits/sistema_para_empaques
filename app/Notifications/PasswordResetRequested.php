<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/** Aviso (campanita) a los administradores: un usuario sin email pidió recuperar su contraseña. */
class PasswordResetRequested extends Notification
{
    public function __construct(public readonly User $user, public readonly ?string $ip = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'password_reset_requested',
            'title' => 'Pedido de recuperación de contraseña',
            'message' => $this->user->full_name.' ('.$this->user->username.') pidió recuperar su contraseña'
                .($this->ip ? ' desde '.$this->ip : '').'. Asignale una contraseña temporal desde su ficha.',
            'url' => route('admin.users.show', $this->user),
            'user_id' => $this->user->id,
        ];
    }
}
