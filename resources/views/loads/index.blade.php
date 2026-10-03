<x-layouts.app title="Cargas">
    <x-page-header title="Cargas" subtitle="Armado, cierre y despacho de camiones.">
        <x-slot:actions>
            @can('loads.create')
                <a href="{{ route('loads.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva carga</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="q" label="Número" :value="request('q')" class="code" placeholder="CARG-…"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="client_id" label="Cliente" :options="$clients" :value="request('client_id')" placeholder="Todos"/>
        <x-select name="destination_id" label="Destino" :options="$destinations" :value="request('destination_id')" placeholder="Todos"/>
        <x-select name="truck_id" label="Camión" :options="$trucks" :value="request('truck_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Destino</th><th>Camión</th><th>Camionero</th><th class="num">Cajones</th><th class="num">Kg</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($loads as $load)
                <tr>
                    <td><a href="{{ route('loads.show', $load) }}" class="code link">{{ $load->number }}</a></td>
                    <td class="tabular-nums">{{ fdate($load->date) }}</td>
                    <td>{{ $load->client?->business_name ?? '—' }}</td>
                    <td>{{ $load->destination?->name ?? '—' }}</td>
                    <td class="code">{{ $load->truck?->plate ?? '—' }}</td>
                    <td>{{ $load->driver?->full_name ?? '—' }}</td>
                    <td class="num">{{ num($load->total_crates) }}</td>
                    <td class="num">{{ num($load->total_kg, 1) }}</td>
                    <td><x-status :status="$load->status"/></td>
                    <td class="text-right whitespace-nowrap">
                        @if ($load->status->isEditable())
                            @can('loads.update')<a href="{{ route('loads.builder', $load) }}" class="link">Armar</a>@endcan
                        @else
                            <a href="{{ route('loads.show', $load) }}" class="link">Ver</a>
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="10"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $loads->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
