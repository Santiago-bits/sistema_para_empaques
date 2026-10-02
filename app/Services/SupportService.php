<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use App\Support\Recipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/** Tickets de soporte entre el galpón y el desarrollador del sistema. */
class SupportService
{
    public const PRIORITIES = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'];

    public const STATUS_COLORS = [
        'open' => 'amber', 'review' => 'blue', 'development' => 'violet', 'resolved' => 'emerald', 'closed' => 'zinc',
    ];

    public function __construct(private readonly SequenceService $sequences)
    {
    }

    public function create(User $user, array $data): SupportTicket
    {
        $ticket = DB::transaction(fn () => SupportTicket::query()->create([
            'number' => $this->sequences->next('ticket'),
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'user_id' => $user->id,
        ]));

        $this->notify(
            Recipients::developers()->reject(fn ($u) => $u->is($user)),
            $ticket,
            'Nuevo ticket '.$ticket->number.': '.Str::limit($ticket->subject, 80),
            'Prioridad '.mb_strtolower(self::PRIORITIES[$ticket->priority] ?? $ticket->priority).' · '.$user->full_name,
        );

        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $user, string $body): SupportTicketReply
    {
        $isDeveloper = $user->can('developer');

        if ($ticket->status === 'closed' && ! $isDeveloper) {
            throw new BusinessException('El ticket está cerrado. Si el problema continúa, creá un ticket nuevo.');
        }

        $reply = DB::transaction(function () use ($ticket, $user, $body, $isDeveloper) {
            $reply = $ticket->replies()->create([
                'user_id' => $user->id,
                'body' => $body,
                'from_developer' => $isDeveloper,
            ]);

            // Si el usuario responde un ticket resuelto, vuelve a revisión.
            if (! $isDeveloper && $ticket->status === 'resolved') {
                $ticket->update(['status' => 'review']);
            } else {
                $ticket->touch();
            }

            return $reply;
        });

        $recipients = $isDeveloper
            ? collect([$ticket->user])->filter()
            : Recipients::developers();

        $this->notify(
            $recipients->reject(fn ($u) => $u->is($user)),
            $ticket,
            ($isDeveloper ? 'Respuesta de soporte en ' : 'Nueva respuesta en ').$ticket->number,
            Str::limit($body, 140),
        );

        return $reply;
    }

    public function changeStatus(SupportTicket $ticket, string $status, User $by): SupportTicket
    {
        if (! array_key_exists($status, SupportTicket::STATUSES)) {
            throw new BusinessException('Estado de ticket inválido.');
        }
        if ($ticket->status === $status) {
            return $ticket;
        }

        $ticket->update(['status' => $status]); // Auditable registra el cambio.

        $this->notify(
            collect([$ticket->user])->filter()->reject(fn ($u) => $u->is($by)),
            $ticket,
            'Ticket '.$ticket->number.': '.SupportTicket::STATUSES[$status],
            Str::limit($ticket->subject, 120),
        );

        return $ticket;
    }

    private function notify($users, SupportTicket $ticket, string $title, ?string $message): void
    {
        try {
            if ($users->isNotEmpty()) {
                Notification::send($users, new SupportTicketUpdated($ticket->id, $ticket->number, $title, $message));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
