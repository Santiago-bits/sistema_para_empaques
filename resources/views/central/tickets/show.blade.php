<x-layouts.app :title="$ticket->remote_number.' · '.$ticket->subject">
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->license->client_name.' · '.$ticket->remote_number.' · pidió '.($ticket->requester ?? '—')"
                   :back="route('central.tickets.index')">
        <x-slot:actions>
            <x-badge :color="\App\Services\SupportService::STATUS_COLORS[$ticket->status] ?? 'stone'" class="text-sm">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</x-badge>
            <a href="{{ route('central.clients.show', $ticket->license) }}" class="btn btn-secondary">Ver cliente</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-panel>
                <p class="text-xs text-stone-500">{{ fdate($ticket->created_remote_at, true) }} · Prioridad {{ mb_strtolower(\App\Services\SupportService::PRIORITIES[$ticket->priority] ?? $ticket->priority) }}</p>
                <p class="mt-2 text-sm whitespace-pre-line">{{ $ticket->description }}</p>
            </x-panel>
            @foreach ($ticket->messages as $m)
                <div @class(['panel p-4', 'ml-8 border-brand-300 dark:border-brand-800' => $m->from_developer, 'mr-8' => ! $m->from_developer])>
                    <p class="text-xs text-stone-500">
                        <strong class="text-stone-700 dark:text-stone-200">{{ $m->author }}</strong>{{ $m->from_developer ? ' (soporte)' : '' }} · {{ fdate($m->created_at, true) }}
                        @if ($m->from_developer) · {{ $m->delivered_at ? 'entregado '.fdate($m->delivered_at, true) : 'pendiente de entrega' }}@endif
                    </p>
                    <p class="mt-1 text-sm whitespace-pre-line">{{ $m->body }}</p>
                </div>
            @endforeach
        </div>

        <x-panel title="Responder">
            <form method="POST" action="{{ route('central.tickets.reply', $ticket) }}" class="space-y-3">
                @csrf
                <x-textarea name="body" label="Respuesta" required rows="6"/>
                <x-select name="status" label="Cambiar estado a" :options="\App\Models\SupportTicket::STATUSES" placeholder="Sin cambios"/>
                <button class="btn btn-primary w-full">Enviar respuesta</button>
            </form>
            <p class="form-hint mt-3">El empaque recibe la respuesta en su próxima sincronización (cada 5 minutos, si tiene internet).</p>
        </x-panel>
    </div>
</x-layouts.app>
