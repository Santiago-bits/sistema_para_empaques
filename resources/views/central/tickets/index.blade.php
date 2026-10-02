@php $statuses = ['pending' => 'Pendientes'] + \App\Models\SupportTicket::STATUSES; @endphp
<x-layouts.app title="Soporte de clientes">
    <x-page-header title="Soporte de clientes" subtitle="Pedidos de los empaques: problemas, cambios y mejoras que piden agregar."/>

    <nav class="mb-4 flex flex-wrap gap-1" aria-label="Estado">
        @foreach ($statuses as $key => $name)
            <a href="{{ route('central.tickets.index', array_filter(['status' => $key, 'license' => request('license')])) }}"
               @class(['rounded-full px-3 py-1 text-sm', 'bg-brand-600 font-medium text-white' => $status === $key, 'bg-stone-100 text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300' => $status !== $key])>{{ $name }}</a>
        @endforeach
    </nav>

    <x-filters>
        <input type="hidden" name="status" value="{{ $status }}">
        <x-select name="license" label="Empaque" :options="$clients" :value="request('license')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Empaque</th><th>N°</th><th>Asunto</th><th>Prioridad</th><th>Estado</th><th>Pidió</th><th>Último mensaje</th></tr></thead>
        <tbody>
            @forelse ($tickets as $t)
                <tr>
                    <td class="font-medium">{{ $t->license?->client_name }}</td>
                    <td class="code">{{ $t->remote_number }}</td>
                    <td><a href="{{ route('central.tickets.show', $t) }}" class="link">{{ $t->subject }}</a></td>
                    <td><x-badge :color="['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'stone'][$t->priority] ?? 'stone'">{{ \App\Services\SupportService::PRIORITIES[$t->priority] ?? $t->priority }}</x-badge></td>
                    <td><x-badge :color="\App\Services\SupportService::STATUS_COLORS[$t->status] ?? 'stone'">{{ \App\Models\SupportTicket::STATUSES[$t->status] ?? $t->status }}</x-badge></td>
                    <td class="text-stone-500">{{ $t->requester ?? '—' }}</td>
                    <td class="whitespace-nowrap text-stone-500">{{ fdate($t->last_message_at, true) }}</td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay pedidos en esta vista."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
