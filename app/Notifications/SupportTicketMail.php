<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ticket de soporte nuevo (o respuesta del galpón) al email de soporte (setting support.email): llega con todo
 * lo necesario para trabajarlo sin entrar al sistema. «Responder» en el correo le escribe a quien lo creó.
 */
class SupportTicketMail extends Notification
{
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly User $author,
        public readonly ?string $reply = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $company = (string) setting('company.name', config('app.name'));
        $priority = SupportService::PRIORITIES[$ticket->priority] ?? $ticket->priority;
        $author = $this->author;

        $mail = (new MailMessage)
            ->subject(($this->reply ? 'Respuesta en ' : 'Nuevo ticket ').$ticket->number.' · '.$company.': '.$ticket->subject)
            ->greeting($this->reply ? 'Nueva respuesta en el ticket '.$ticket->number : 'Nuevo ticket de soporte '.$ticket->number)
            ->line('**Empresa:** '.$company.' ('.config('app.url').')')
            ->line('**Asunto:** '.$ticket->subject)
            ->line('**Prioridad:** '.$priority)
            ->line('**De:** '.$author->full_name.' · usuario '.$author->username
                .($author->email ? ' · '.$author->email : '').($author->phone ? ' · tel. '.$author->phone : ''));

        if ($this->reply) {
            $mail->line('**Respuesta:**');
            $this->paragraphs($mail, $this->reply);
        } else {
            $mail->line('**Descripción:**');
            $this->paragraphs($mail, (string) $ticket->description);
        }

        if ($author->email) {
            $mail->replyTo($author->email, $author->full_name);
        }

        return $mail->action('Abrir el ticket en el sistema', route('support.show', $ticket->id))
            ->line($author->email ? 'Si respondés este correo, le llega a '.$author->full_name.'.' : 'Respondé desde el sistema: quien lo creó no tiene email cargado.');
    }

    private function paragraphs(MailMessage $mail, string $text): void
    {
        foreach (preg_split("/\R{2,}/", trim($text)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }
    }
}
