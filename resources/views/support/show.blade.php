@php
    $statuses = \App\Models\SupportTicket::STATUSES;
    $colors = \App\Services\SupportService::STATUS_COLORS;
@endphp
<x-layouts.app :title="'Ticket '.$ticket->number">
    <x-page-header :title="$ticket->number.' · '.$ticket->subject" :back="route('support.index')">
        <x-slot:actions>
            <x-badge :color="$colors[$ticket->status] ?? 'stone'">{{ $statuses[$ticket->status] }}</x-badge>
            @if ($isDeveloper)
                <form method="POST" action="{{ route('support.status', $ticket) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="status" class="form-input py-1.5 text-sm" aria-label="Cambiar estado">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary btn-sm">Cambiar</button>
                </form>
            @elseif ($ticket->status !== 'closed')
                <form method="POST" action="{{ route('support.status', $ticket) }}" x-data x-confirm="¿Cerrar el ticket? Usalo cuando el problema quedó resuelto.">
                    @csrf
                    <input type="hidden" name="status" value="closed">
                    <button class="btn btn-secondary btn-sm">Cerrar ticket</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        <article class="panel p-5">
            <p class="text-xs text-stone-500">{{ $ticket->user?->full_name }} · {{ fdate($ticket->created_at, true) }} · Prioridad {{ mb_strtolower(\App\Services\SupportService::PRIORITIES[$ticket->priority] ?? $ticket->priority) }}</p>
            <div class="mt-2 text-sm whitespace-pre-line text-stone-800 dark:text-stone-200">{{ $ticket->description }}</div>
        </article>

        @foreach ($ticket->replies as $reply)
            <article @class(['panel p-5', 'border-brand-300 bg-brand-50/50 dark:border-brand-900 dark:bg-brand-950/20' => $reply->from_developer])>
                <p class="text-xs text-stone-500">
                    {{ $reply->user?->full_name }} @if ($reply->from_developer)<x-badge color="emerald">Soporte</x-badge>@endif · {{ fdate($reply->created_at, true) }}
                </p>
                <div class="mt-2 text-sm whitespace-pre-line text-stone-800 dark:text-stone-200">{{ $reply->body }}</div>
            </article>
        @endforeach

        @if ($ticket->status !== 'closed' || $isDeveloper)
            <x-panel title="Responder">
                <form method="POST" action="{{ route('support.reply', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-textarea name="body" rows="4" required maxlength="5000"/>
                    <div class="flex justify-end"><button class="btn btn-primary">Enviar respuesta</button></div>
                </form>
            </x-panel>
        @else
            <p class="text-center text-sm text-stone-500">El ticket está cerrado. Si el problema volvió, creá uno nuevo.</p>
        @endif
    </div>
</x-layouts.app>
