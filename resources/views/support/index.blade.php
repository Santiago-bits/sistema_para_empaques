@php
    $statuses = \App\Models\SupportTicket::STATUSES;
    $colors = \App\Services\SupportService::STATUS_COLORS;
    $priorities = \App\Services\SupportService::PRIORITIES;
@endphp
<x-layouts.app title="Soporte">
    <x-page-header title="Soporte técnico" :subtitle="$isDeveloper ? 'Todos los tickets del galpón' : 'Tus consultas y reportes de problemas'">
        <x-slot:actions>
            <a href="{{ route('support.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo ticket</a>
        </x-slot:actions>
    </x-page-header>

    @if ($central['enabled'])
        <div class="panel mb-4 flex flex-wrap items-center gap-3 p-4 text-sm">
            <x-icon name="signal" class="size-5 {{ $central['error'] ? 'text-amber-500' : 'text-emerald-600' }}"/>
            <div class="min-w-0 flex-1">
                <p class="font-medium text-stone-900 dark:text-white">Conectado con el soporte del proveedor del sistema</p>
                <p class="text-stone-500">
                    Tus tickets le llegan al administrador general para resolver problemas o agregar lo que necesites.
                    @if ($central['last_sync']) Última sincronización: {{ fdate(\Illuminate\Support\Carbon::parse($central['last_sync']), true) }}.@endif
                    @if ($central['error']) <span class="text-amber-700 dark:text-amber-400">Último intento sin conexión.</span>@endif
                </p>
            </div>
            <form method="POST" action="{{ route('support.sync') }}">@csrf<button class="btn btn-secondary btn-sm"><x-icon name="refresh" class="size-4"/> Sincronizar ahora</button></form>
        </div>
    @endif

    <x-filters>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Ticket</th><th>Asunto</th><th>Prioridad</th><th>Estado</th>@if ($isDeveloper)<th>Usuario</th>@endif<th class="num">Respuestas</th><th>Actualizado</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('support.show', $ticket) }}" class="link code">{{ $ticket->number }}</a></td>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $ticket->subject }}</td>
                    <td>{{ $priorities[$ticket->priority] ?? $ticket->priority }}</td>
                    <td><x-badge :color="$colors[$ticket->status] ?? 'stone'">{{ $statuses[$ticket->status] ?? $ticket->status }}</x-badge></td>
                    @if ($isDeveloper)<td>{{ $ticket->user?->full_name }}</td>@endif
                    <td class="num">{{ $ticket->replies_count }}</td>
                    <td class="text-stone-500">{{ $ticket->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay tickets. Si algo no funciona como esperás, creá uno."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
