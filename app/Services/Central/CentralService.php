<?php

namespace App\Services\Central;

use App\Exceptions\BusinessException;
use App\Models\ClientTicket;
use App\Models\ClientTicketMessage;
use App\Models\License;
use App\Models\SupportTicket;
use App\Models\UsageReport;
use App\Models\User;
use App\Support\Recipients;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Panel General (servidor del proveedor): recibe el uso y los pedidos de soporte de cada empaque y
 * guarda las respuestas del proveedor hasta que el empaque las descarga (el empaque siempre inicia
 * la conexión: no hace falta abrir puertos en la red del galpón).
 */
class CentralService
{
    /** Métricas aceptadas (todo lo demás se descarta). Sólo totales: nunca datos de personas. */
    public const METRICS = [
        'users_total', 'users_active_7d', 'logins_today', 'crates_today', 'crates_30d', 'kg_30d', 'loads_30d',
        'invoices_30d', 'open_tickets', 'errors_7d', 'last_activity_at', 'modules', 'php', 'db',
    ];

    public function recordReport(License $license, array $metrics, ?string $version, ?string $ip): UsageReport
    {
        $clean = array_intersect_key($metrics, array_flip(self::METRICS));
        foreach ($clean as $key => $value) {
            if (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 60);
            } elseif (is_array($value)) {
                $clean[$key] = array_slice(array_values(array_filter($value, 'is_string')), 0, 40);
            } elseif (! is_int($value) && ! is_float($value)) {
                unset($clean[$key]);
            }
        }

        return DB::transaction(function () use ($license, $clean, $version, $ip) {
            $license->forceFill(['last_seen_at' => now(), 'version' => $version ? mb_substr($version, 0, 20) : $license->version])->save();

            return UsageReport::query()->create([
                'license_id' => $license->id, 'reported_at' => now(), 'version' => $version, 'metrics' => $clean, 'ip' => $ip,
            ]);
        });
    }

    /** Alta o actualización de un ticket del empaque con sus mensajes (idempotente). */
    public function upsertTicket(License $license, array $data): ClientTicket
    {
        [$ticket, $isNew, $newMessages] = DB::transaction(function () use ($license, $data) {
            $ticket = ClientTicket::query()->where('license_id', $license->id)->where('remote_number', $data['number'])->lockForUpdate()->first();
            $isNew = $ticket === null;
            if ($isNew) {
                $ticket = ClientTicket::query()->create([
                    'license_id' => $license->id,
                    'remote_number' => $data['number'],
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'priority' => $data['priority'] ?? 'medium',
                    'status' => array_key_exists($data['status'] ?? '', SupportTicket::STATUSES) ? $data['status'] : 'open',
                    'requester' => $data['requester'] ?? null,
                    'created_remote_at' => isset($data['created_at']) ? Carbon::parse($data['created_at']) : now(),
                    'last_message_at' => now(),
                ]);
            }

            $newMessages = 0;
            foreach ($data['replies'] ?? [] as $reply) {
                $exists = ClientTicketMessage::query()->where('client_ticket_id', $ticket->id)->where('remote_reply_id', $reply['id'])->exists();
                if ($exists) {
                    continue;
                }
                ClientTicketMessage::query()->create([
                    'client_ticket_id' => $ticket->id, 'remote_reply_id' => $reply['id'], 'author' => mb_substr($reply['author'] ?? 'Cliente', 0, 160),
                    'body' => $reply['body'], 'from_developer' => false,
                ]);
                $newMessages++;
            }
            if ($newMessages > 0) {
                // El cliente respondió: si estaba resuelto, vuelve a revisión.
                $ticket->forceFill(['last_message_at' => now()] + ($ticket->status === 'resolved' ? ['status' => 'review', 'status_pending' => true] : []))->save();
            }

            return [$ticket, $isNew, $newMessages];
        });

        if ($isNew || $newMessages > 0) {
            $this->notifyDevelopers($ticket, $isNew
                ? 'Pedido de soporte de '.$license->client_name.': '.Str::limit($ticket->subject, 70)
                : 'Respuesta de '.$license->client_name.' en '.$ticket->remote_number);
        }

        return $ticket;
    }

    public function reply(ClientTicket $ticket, User $by, string $body, ?string $status = null): ClientTicketMessage
    {
        if ($status !== null && ! array_key_exists($status, SupportTicket::STATUSES)) {
            throw new BusinessException('Estado inválido.');
        }

        return DB::transaction(function () use ($ticket, $by, $body, $status) {
            $message = ClientTicketMessage::query()->create([
                'client_ticket_id' => $ticket->id, 'user_id' => $by->id, 'author' => $by->full_name, 'body' => $body, 'from_developer' => true,
            ]);
            $ticket->forceFill(['last_message_at' => now()]
                + ($status && $status !== $ticket->status ? ['status' => $status, 'status_pending' => true] : []))->save();

            return $message;
        });
    }

    public function changeStatus(ClientTicket $ticket, string $status): void
    {
        if (! array_key_exists($status, SupportTicket::STATUSES)) {
            throw new BusinessException('Estado inválido.');
        }
        $ticket->update(['status' => $status, 'status_pending' => true]);
    }

    /** Novedades pendientes de entregar al empaque: licencia, respuestas y cambios de estado. */
    public function updatesFor(License $license): array
    {
        $messages = ClientTicketMessage::query()
            ->whereHas('ticket', fn ($q) => $q->where('license_id', $license->id))
            ->where('from_developer', true)->whereNull('delivered_at')->with('ticket:id,remote_number')->orderBy('id')->limit(100)->get();

        return [
            'license' => [
                'client_name' => $license->client_name, 'plan' => $license->plan, 'status' => $license->status,
                'starts_on' => $license->starts_on?->toDateString(), 'expires_on' => $license->expires_on?->toDateString(),
                'modules' => $license->modules,
            ],
            'messages' => $messages->map(fn (ClientTicketMessage $m) => [
                'id' => $m->id, 'ticket' => $m->ticket->remote_number, 'author' => $m->author, 'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all(),
            'statuses' => ClientTicket::query()->where('license_id', $license->id)->where('status_pending', true)
                ->get(['remote_number', 'status'])->map(fn ($t) => ['ticket' => $t->remote_number, 'status' => $t->status])->values()->all(),
        ];
    }

    /** El empaque confirma lo que guardó: recién ahí se marca como entregado. */
    public function acknowledge(License $license, array $messageIds, array $tickets): void
    {
        DB::transaction(function () use ($license, $messageIds, $tickets) {
            ClientTicketMessage::query()->whereIn('id', array_map('intval', $messageIds))
                ->whereHas('ticket', fn ($q) => $q->where('license_id', $license->id))
                ->whereNull('delivered_at')->update(['delivered_at' => now()]);
            ClientTicket::query()->where('license_id', $license->id)->whereIn('remote_number', $tickets)->update(['status_pending' => false]);
        });
    }

    private function notifyDevelopers(ClientTicket $ticket, string $title): void
    {
        try {
            $users = Recipients::developers();
            if ($users->isNotEmpty()) {
                Notification::send($users, new \App\Notifications\ClientTicketReceived($ticket->id, $title));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
