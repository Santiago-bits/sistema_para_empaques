<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;

/** Novedad en un ticket de soporte (respuesta o cambio de estado). */
class SupportTicketUpdated extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $number,
        public readonly string $title,
        public readonly ?string $message = null,
    ) {
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            'support',
            $this->title,
            $this->message,
            self::safeRoute('support.show', $this->ticketId),
            'info',
            ['ticket_id' => $this->ticketId, 'number' => $this->number],
        );
    }
}
