<x-layouts.app title="Ingresos de fruta">
    <x-page-header title="Ingresos de fruta (lotes)" subtitle="Lo que entra de cada quinta: fecha, productor, especie, variedad, chofer, bines y DTV. Cada pallet y cajón queda vinculado a su lote.">
        <x-slot:actions>
            <a href="{{ route('lots.index', ['season' => 'current']) }}" class="btn btn-secondary">Ingresos de la temporada</a>
            @can('lots.manage')
                <a href="{{ route('lots.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo ingreso</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-3 gap-3">
        <x-stat label="Ingresos" :value="num($totals->n ?? 0)" icon="layers"/>
        <x-stat label="Bines" :value="num($totals->bins ?? 0)" icon="archive" color="sky"/>
        <x-stat label="Kilos" :value="kg($totals->kg ?? 0, 0)" icon="scale" color="accent"/>
    </div>

    <x-filters :exports="[['label' => 'Excel', 'format' => 'xlsx', 'route' => route('lots.index')]]">
        <x-input name="q" label="Código" :value="request('q')" placeholder="LOT-…"/>
        <x-select name="producer_id" label="Quinta / productor" :options="$producers" :value="request('producer_id')" placeholder="Todos"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="driver_id" label="Chofer" :options="$drivers" :value="request('driver_id')" placeholder="Todos"/>
        <x-select name="season" label="Temporada" :options="['current' => 'Sólo la temporada en curso']" :value="request('season')" placeholder="Todas"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Fecha</th><th>Lote</th><th>Quinta / productor</th><th>Especie</th><th>Variedad</th><th>Chofer</th><th class="num">Bines</th><th class="num">Kilos</th><th>DTV-e</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($lots as $lot)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ fdate($lot->date) }}</td>
                    <td><a href="{{ route('lots.show', $lot) }}" class="code link">{{ $lot->code }}</a></td>
                    <td>{{ $lot->producer?->name }}</td>
                    <td>{{ $lot->variety?->species ?? '—' }}</td>
                    <td>{{ $lot->variety?->name ?? '—' }}</td>
                    <td>{{ $lot->driver?->full_name ?? '—' }}</td>
                    <td class="num">{{ $lot->bins !== null ? num($lot->bins) : '—' }}</td>
                    <td class="num">{{ $lot->kg_received !== null ? num($lot->kg_received, 0) : '—' }}</td>
                    <td class="code text-sm">{{ $lot->dtv_number ?: '—' }}</td>
                    <td><x-badge :color="['open' => 'emerald', 'closed' => 'blue', 'voided' => 'zinc'][$lot->status] ?? 'stone'">{{ \App\Models\Lot::STATUSES[$lot->status] ?? $lot->status }}</x-badge></td>
                </tr>
            @empty
                <x-empty colspan="10" message="Todavía no hay ingresos de fruta. Tocá «Nuevo ingreso»."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $lots->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
