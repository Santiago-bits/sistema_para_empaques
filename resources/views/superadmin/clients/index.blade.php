<x-layouts.app title="Administración general · Clientes">
    <x-page-header title="Clientes y pagos" subtitle="Si cada empaque pagó, hasta cuándo y cuánto usa el sistema.">
        <x-slot:actions>
            <a href="{{ route('central.clients.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo cliente</a>
        </x-slot:actions>
    </x-page-header>

    @include('superadmin._nav')

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Empaque, contacto, localidad"/>
        <x-select name="payment" label="Pago" :options="collect(\App\Models\License::PAYMENT_STATUSES)->map(fn ($s) => $s[0])->all()" :value="request('payment')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Empaque</th><th>Contacto</th><th>Pago</th><th class="num">Cuota</th><th>Uso del sistema</th><th class="num">Soporte</th></tr></thead>
        <tbody>
            @forelse ($clients as $client)
                @php $r = $client->latestReport; @endphp
                <tr>
                    <td>
                        <a href="{{ route('superadmin.clients.show', $client) }}" class="font-medium text-stone-900 hover:underline dark:text-white">{{ $client->client_name }}</a>
                        <span class="block text-xs text-stone-500">{{ $client->locality }}</span>
                    </td>
                    <td class="text-sm">
                        {{ $client->contact_name ?: '—' }}
                        <span class="block text-xs text-stone-500">{{ collect([$client->contact_phone, $client->contact_email])->filter()->join(' · ') }}</span>
                    </td>
                    <td>
                        <x-badge :color="$client->paymentColor()">{{ $client->paymentLabel() }}</x-badge>
                        @if ($client->paid_until)
                            <span class="block text-xs text-stone-500">hasta {{ fdate($client->paid_until) }}</span>
                        @endif
                    </td>
                    <td class="num text-sm whitespace-nowrap">{{ $client->monthly_fee ? money($client->monthly_fee, $client->fee_currency) : '—' }}</td>
                    <td class="text-sm">
                        @if ($client->isOnline())
                            <span class="font-medium text-emerald-700 dark:text-emerald-400">● Conectado</span>
                        @elseif ($client->last_seen_at)
                            <span class="text-amber-700 dark:text-amber-400">● {{ $client->last_seen_at->diffForHumans() }}</span>
                        @else
                            <span class="text-stone-400">● Nunca se conectó</span>
                        @endif
                        @if ($r)
                            <span class="block text-xs text-stone-500">{{ num($r->metric('users_active_7d')) }} usuarios activos · {{ num($r->metric('crates_30d')) }} cajones en 30 días</span>
                        @endif
                    </td>
                    <td class="num">
                        @if ($client->open_tickets_count)
                            <x-badge color="amber">{{ $client->open_tickets_count }}</x-badge>
                        @else
                            <span class="text-stone-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty :colspan="6" message="Todavía no cargaste clientes. Tocá «Nuevo cliente» para dar de alta el primer empaque."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
