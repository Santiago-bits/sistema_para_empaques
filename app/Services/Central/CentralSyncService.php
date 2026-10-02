<?php

namespace App\Services\Central;

use App\Models\License;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Notifications\SupportTicketUpdated;
use App\Services\ModuleService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Del lado de cada empaque: conexión con el Panel General del proveedor.
 *  - envía un reporte de uso (sólo totales) como máximo una vez por hora;
 *  - sube los tickets de soporte y las respuestas de sus usuarios;
 *  - baja las respuestas del proveedor, los cambios de estado y el estado de la licencia.
 * Si no hay internet no pasa nada: se reintenta en la próxima ejecución (cada 5 minutos).
 */
class CentralSyncService
{
    public const LAST_SYNC_KEY = 'galpon.central.last_sync';

    public const LAST_REPORT_KEY = 'galpon.central.last_report';

    public const LAST_ERROR_KEY = 'galpon.central.last_error';

    public function enabled(): bool
    {
        return ! config('galpon.central.mode') && filled(config('galpon.central.url')) && filled(config('galpon.central.key'));
    }

    /** @return array{report: bool, tickets: int, messages: int, statuses: int} */
    public function sync(bool $forceReport = false): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('La conexión con el Panel General no está configurada (GALPON_CENTRAL_URL y GALPON_LICENSE_KEY).');
        }

        try {
            $reported = false;
            if ($forceReport || ! Cache::has(self::LAST_REPORT_KEY)) {
                $this->client()->post('reportes', ['version' => config('galpon.version'), 'metrics' => $this->metrics()])->throw();
                Cache::put(self::LAST_REPORT_KEY, now()->toIso8601String(), now()->addMinutes(55));
                $reported = true;
            }

            $tickets = $this->pushTickets();
            [$messages, $statuses] = $this->pullUpdates();

            Cache::forever(self::LAST_SYNC_KEY, now()->toIso8601String());
            Cache::forget(self::LAST_ERROR_KEY);

            return ['report' => $reported, 'tickets' => $tickets, 'messages' => $messages, 'statuses' => $statuses];
        } catch (Throwable $e) {
            Cache::forever(self::LAST_ERROR_KEY, Str::limit($e->getMessage(), 200));
            throw $e;
        }
    }

    /** Totales de uso: nunca nombres, documentos ni datos de clientes. */
    public function metrics(): array
    {
        $since30 = now()->subDays(30);

        return [
            'users_total' => DB::table('users')->where('status', 'active')->count(),
            'users_active_7d' => DB::table('users')->where('last_login_at', '>=', now()->subDays(7))->count(),
            'logins_today' => DB::table('users')->whereDate('last_login_at', today()->toDateString())->count(),
            'crates_today' => DB::table('production_records')->whereNull('voided_at')->whereDate('recorded_at', today()->toDateString())->count(),
            'crates_30d' => DB::table('production_records')->whereNull('voided_at')->where('recorded_at', '>=', $since30)->count(),
            'kg_30d' => round((float) DB::table('production_records')->whereNull('voided_at')->where('recorded_at', '>=', $since30)->sum('weight'), 2),
            'loads_30d' => DB::table('loads')->whereNull('deleted_at')->where('created_at', '>=', $since30)->count(),
            'invoices_30d' => DB::table('invoices')->where('status', 'authorized')->where('created_at', '>=', $since30)->count(),
            'open_tickets' => DB::table('support_tickets')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'errors_7d' => DB::table('system_errors')->where('created_at', '>=', now()->subDays(7))->count(),
            'last_activity_at' => (string) DB::table('audit_logs')->max('created_at'),
            'modules' => array_keys(array_filter(app(ModuleService::class)->states())),
            'php' => PHP_VERSION,
            'db' => DB::connection()->getDriverName(),
        ];
    }

    private function pushTickets(): int
    {
        $pushed = 0;
        SupportTicket::query()->with(['user:id,first_name,last_name', 'replies.user:id,first_name,last_name'])
            ->where(fn ($q) => $q->whereNull('central_synced_at')->orWhereColumn('updated_at', '>', 'central_synced_at')
                ->orWhereHas('replies', fn ($r) => $r->where('from_developer', false)->whereNull('central_synced_at')))
            ->orderBy('id')->limit(50)->get()
            ->each(function (SupportTicket $ticket) use (&$pushed) {
                $replies = $ticket->replies->where('from_developer', false)->whereNull('central_id');
                $this->client()->post('tickets', [
                    'number' => $ticket->number,
                    'subject' => $ticket->subject,
                    'description' => $ticket->description,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'requester' => $ticket->user?->full_name,
                    'created_at' => $ticket->created_at?->toIso8601String(),
                    'replies' => $replies->map(fn (SupportTicketReply $r) => [
                        'id' => $r->id, 'author' => $r->user?->full_name, 'body' => $r->body,
                    ])->values()->all(),
                ])->throw();

                // Sin tocar updated_at (si no, el ticket volvería a subirse siempre).
                $now = now();
                SupportTicket::query()->whereKey($ticket->id)->toBase()->update(['central_synced_at' => $now, 'updated_at' => $ticket->updated_at]);
                SupportTicketReply::query()->whereIn('id', $replies->pluck('id'))->update(['central_synced_at' => $now]);
                $pushed++;
            });

        return $pushed;
    }

    /** @return array{0: int, 1: int} */
    private function pullUpdates(): array
    {
        $data = $this->client()->get('novedades')->throw()->json();

        $this->storeLicense((array) ($data['license'] ?? []));

        $received = [];
        $notify = [];
        foreach ((array) ($data['messages'] ?? []) as $message) {
            $ticket = SupportTicket::query()->where('number', (string) ($message['ticket'] ?? ''))->first();
            if (! $ticket) {
                $received[] = (int) $message['id']; // ticket inexistente acá: se confirma igual para no reenviarlo siempre
                continue;
            }
            $exists = SupportTicketReply::query()->where('central_id', (int) $message['id'])->exists();
            if (! $exists) {
                SupportTicketReply::query()->create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id, // la respuesta es del proveedor; se asocia al ticket (from_developer = true)
                    'body' => Str::limit((string) $message['body'], 10000, ''),
                    'from_developer' => true,
                    'central_id' => (int) $message['id'],
                    'central_synced_at' => now(),
                ]);
                $notify[$ticket->id] = [$ticket, 'Respuesta de soporte en '.$ticket->number, Str::limit((string) $message['body'], 140)];
            }
            $received[] = (int) $message['id'];
        }

        $tickets = [];
        foreach ((array) ($data['statuses'] ?? []) as $change) {
            $ticket = SupportTicket::query()->where('number', (string) ($change['ticket'] ?? ''))->first();
            $status = (string) ($change['status'] ?? '');
            if ($ticket && array_key_exists($status, SupportTicket::STATUSES) && $ticket->status !== $status) {
                SupportTicket::query()->whereKey($ticket->id)->toBase()->update(['status' => $status]);
                $notify[$ticket->id] ??= [$ticket, 'Ticket '.$ticket->number.': '.SupportTicket::STATUSES[$status], Str::limit($ticket->subject, 120)];
            }
            $tickets[] = (string) ($change['ticket'] ?? '');
        }

        if ($received !== [] || $tickets !== []) {
            $this->client()->post('novedades/recibidas', ['messages' => $received, 'tickets' => $tickets])->throw();
        }

        foreach ($notify as [$ticket, $title, $message]) {
            try {
                if ($ticket->user) {
                    Notification::send($ticket->user, new SupportTicketUpdated($ticket->id, $ticket->number, $title, $message));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return [count($received), count($tickets)];
    }

    /** El Panel General es la fuente de verdad de la licencia de este empaque (sólo informativa: nunca bloquea). */
    private function storeLicense(array $license): void
    {
        if ($license === [] || ! isset($license['client_name'])) {
            return;
        }
        License::query()->updateOrCreate(['installation_id' => config('galpon.installation_id')], [
            'client_name' => mb_substr((string) $license['client_name'], 0, 255),
            'license_key' => (string) config('galpon.central.key'),
            'plan' => mb_substr((string) ($license['plan'] ?? 'standard'), 0, 30),
            'status' => in_array($license['status'] ?? '', ['active', 'suspended', 'expired'], true) ? $license['status'] : 'active',
            'starts_on' => $license['starts_on'] ?? today()->toDateString(),
            'expires_on' => $license['expires_on'] ?? null,
            'modules' => is_array($license['modules'] ?? null) ? $license['modules'] : null,
            'version' => config('galpon.version'),
            'last_seen_at' => now(),
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('galpon.central.url'), '/').'/api/central/v1/')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('galpon.central.timeout', 10))
            ->withToken((string) config('galpon.central.key'))
            ->withHeaders(['X-Installation-Id' => (string) config('galpon.installation_id'), 'X-Galpon-Version' => (string) config('galpon.version')]);
    }
}
