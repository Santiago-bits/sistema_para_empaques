@php $statuses = \App\Http\Controllers\Developer\LicenseController::STATUSES; @endphp
<x-layouts.app title="Panel general · Clientes">
    <x-page-header title="Clientes y uso" subtitle="Empaques que usan el sistema, cuánto lo usan y su licencia.">
        <x-slot:actions>
            <a href="{{ route('central.tickets.index') }}" class="btn btn-secondary"><x-icon name="lifebuoy" class="size-4"/> Soporte ({{ $stats['open_tickets'] }})</a>
            <a href="{{ route('central.clients.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo cliente</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Clientes" :value="num($stats['clients'])" icon="briefcase"/>
        <x-stat label="Conectados (24 h)" :value="num($stats['online'])" icon="signal" color="sky"/>
        <x-stat label="Usuarios activos (7 días)" :value="num($stats['users_active'])" icon="users" color="violet"/>
        <x-stat label="Cajones (30 días)" :value="num($stats['crates_30d'])" icon="box" color="accent"/>
        <x-stat label="Soporte pendiente" :value="num($stats['open_tickets'])" icon="lifebuoy" :color="$stats['open_tickets'] ? 'amber' : 'stone'" :href="route('central.tickets.index')"/>
        <x-stat label="Vencen en 30 días" :value="num($stats['expiring'])" icon="clock" :color="$stats['expiring'] ? 'red' : 'stone'"/>
    </div>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Empaque, localidad, ID"/>
        <x-select name="status" label="Licencia" :options="$statuses" :value="request('status')" placeholder="Todas"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Empaque</th><th>Conexión</th><th>Versión</th><th class="num">Usuarios activos</th><th class="num">Cajones hoy</th><th class="num">Cajones 30 d</th><th class="num">Soporte</th><th>Licencia</th></tr></thead>
        <tbody>
            @forelse ($clients as $client)
                @php $r = $client->latestReport; $status = $client->effectiveStatus(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('central.clients.show', $client) }}" class="font-medium text-stone-900 hover:underline dark:text-white">{{ $client->client_name }}</a>
                        <span class="block text-xs text-stone-500">{{ $client->locality ?? '' }} <span class="code">{{ $client->installation_id }}</span></span>
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        @if ($client->isOnline())
                            <span class="font-medium text-emerald-700 dark:text-emerald-400">● Conectado</span>
                        @elseif ($client->last_seen_at)
                            <span class="text-amber-700 dark:text-amber-400">● {{ $client->last_seen_at->diffForHumans() }}</span>
                        @else
                            <span class="text-stone-400">● Nunca se conectó</span>
                        @endif
                    </td>
                    <td class="code text-xs">{{ $client->version ?? '—' }}</td>
                    <td class="num">{{ $r ? num($r->metric('users_active_7d')).' / '.num($r->metric('users_total')) : '—' }}</td>
                    <td class="num">{{ $r ? num($r->metric('crates_today')) : '—' }}</td>
                    <td class="num">{{ $r ? num($r->metric('crates_30d')) : '—' }}</td>
                    <td class="num">@if ($client->open_tickets_count)<x-badge color="amber">{{ $client->open_tickets_count }}</x-badge>@else <span class="text-stone-400">0</span>@endif</td>
                    <td>
                        <x-badge :color="['active' => 'emerald', 'suspended' => 'red', 'expired' => 'amber'][$status] ?? 'stone'">{{ $statuses[$status] ?? $status }}</x-badge>
                        @if ($client->expires_on)<span class="block text-xs text-stone-500">hasta {{ fdate($client->expires_on) }}</span>@endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="8" message="Todavía no cargaste clientes. Tocá «Nuevo cliente»."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $clients->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
