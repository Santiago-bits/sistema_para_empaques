<x-layouts.app title="Remitos">
    <x-page-header title="Remitos" subtitle="Se emiten desde una carga cerrada. Incluyen QR de consulta."/>

    <x-filters>
        <x-input name="q" label="Número" :value="request('q')" class="code"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="client_id" label="Cliente" :options="$clients" :value="request('client_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Número</th><th>Emisión</th><th>Carga</th><th>Cliente</th><th>Destino</th><th>Camión</th><th class="num">Cajones</th><th class="num">Kg</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @forelse ($remitos as $remito)
                <tr>
                    <td><a href="{{ route('remitos.show', $remito) }}" class="code link">{{ $remito->number }}</a></td>
                    <td class="tabular-nums">{{ fdate($remito->issued_at, true) }}</td>
                    <td class="code">{{ $remito->loadRecord?->number }}</td>
                    <td>{{ $remito->client?->business_name ?? '—' }}</td>
                    <td>{{ $remito->destination?->name ?? '—' }}</td>
                    <td class="code">{{ $remito->truck?->plate ?? '—' }}</td>
                    <td class="num">{{ num($remito->total_crates) }}</td>
                    <td class="num">{{ num($remito->total_kg, 1) }}</td>
                    <td><x-status :status="$remito->status"/></td>
                    <td class="text-right"><a href="{{ route('remitos.pdf', $remito) }}" class="link">PDF</a></td>
                </tr>
            @empty
                <x-empty colspan="10"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $remitos->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
