<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Panel General: llegó un pedido de soporte (o una respuesta) de un empaque. */
class ClientTicketReceived extends Notification
{
    public function __construct(public readonly int $ticketId, public readonly string $title)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'client_ticket',
            'title' => $this->title,
            'message' => 'Respondelo desde Panel general → Soporte de clientes.',
            'url' => route('central.tickets.show', $this->ticketId),
            'ticket_id' => $this->ticketId,
        ];
    }
}
