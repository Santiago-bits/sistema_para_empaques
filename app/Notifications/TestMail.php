<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo de prueba desde Herramientas técnicas: confirma que el servidor puede mandar correos. */
class TestMail extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Prueba de correo · '.setting('company.name', config('app.name')))
            ->greeting('¡El correo funciona!')
            ->line('Este mensaje confirma que el sistema ('.config('app.url').') puede mandar correos.')
            ->line('Acá te van a llegar los tickets de soporte y los avisos de errores.');
    }
}
