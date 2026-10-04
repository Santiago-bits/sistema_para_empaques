<?php

namespace App\Notifications;

use App\Services\PasswordResetService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/** Email con el código de 6 dígitos para recuperar la contraseña (un solo uso, vence en CODE_MINUTES). */
class PasswordResetCode extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = PasswordResetService::CODE_MINUTES;

        return (new MailMessage)
            ->subject('Tu código para recuperar la contraseña — '.setting('company.name', config('app.name')))
            ->greeting('Hola '.$notifiable->first_name.',')
            ->line('Recibimos un pedido para recuperar la contraseña de tu usuario «'.$notifiable->username.'». Ingresá este código en el sistema:')
            ->line(new HtmlString('<p style="margin:24px 0;text-align:center;font-size:32px;font-weight:700;letter-spacing:8px;font-family:monospace">'.e($this->code).'</p>'))
            ->line("El código vence en {$minutes} minutos y se puede usar una sola vez.")
            ->line('Si no lo pediste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.')
            ->salutation('Sistema de gestión del galpón');
    }
}
