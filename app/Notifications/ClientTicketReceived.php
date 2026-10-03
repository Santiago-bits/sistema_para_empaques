<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Panel General: llegó un pedido de soporte (o una respuesta) de un empaque. Aparece en la campanita y en
 * la administración general; si el super admin tiene email y el envío de correos está configurado, también
 * le llega por email.
 */
class ClientTicketReceived extends Notification
{
    public function __construct(public readonly int $ticketId, public readonly string $title)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (filled($notifiable->email ?? null) && ! in_array(config('mail.default'), ['log', 'array', null], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'client_ticket',
            'title' => $this->title,
            'message' => 'Respondelo desde Administración general → Soporte.',
            'url' => route('central.tickets.show', $this->ticketId),
            'ticket_id' => $this->ticketId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Soporte: '.$this->title)
            ->greeting('Nuevo pedido de soporte')
            ->line($this->title)
            ->action('Ver el pedido', route('central.tickets.show', $this->ticketId))
            ->line('Te llega porque sos el administrador general del sistema.');
    }
}
