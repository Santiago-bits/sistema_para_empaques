<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email con el enlace para crear una contraseña nueva (un solo uso, vence según config/auth.php). */
class ResetPasswordLink extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Recuperar contraseña — '.setting('company.name', config('app.name')))
            ->greeting('Hola '.$notifiable->first_name.',')
            ->line('Recibimos un pedido para recuperar la contraseña de tu usuario «'.$notifiable->username.'».')
            ->action('Crear contraseña nueva', $url)
            ->line("El enlace vence en {$minutes} minutos y se puede usar una sola vez.")
            ->line('Si no lo pediste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.')
            ->salutation('Sistema de gestión del galpón');
    }
}
