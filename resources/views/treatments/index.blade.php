<x-layouts.app title="Tratamientos">
    <x-page-header title="Tratamientos" subtitle="Fruta tratada (por ejemplo para entrar a la Patagonia): destino, cantidad, tipo de tratamiento y empresa.">
        <x-slot:actions>
            @can('treatments.manage')
                <a href="{{ route('treatments.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo tratamiento</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($totals->isNotEmpty())
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($totals as $t)
                <x-stat :label="$t->type" :value="num($t->qty, 0)" icon="check-badge" :hint="$t->n.' '.($t->n == 1 ? 'tratamiento' : 'tratamientos')"/>
            @endforeach
        </div>
    @endif

    <x-filters :exports="[['label' => 'Excel', 'format' => 'xlsx', 'route' => route('treatments.index')]]">
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="Destino, empresa, DTV"/>
        <x-select name="type" label="Tipo" :options="array_combine($types, $types)" :value="request('type')" placeholder="Todos"/>
        <x-select name="client_id" label="Cliente" :options="$clients" :value="request('client_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Fecha</th><th>Cliente</th><th>Destino</th><th class="num">Cantidad</th><th>Tratamiento</th><th>Empresa</th><th>DTV-e</th><th></th></tr></thead>
        <tbody>
            @forelse ($treatments as $t)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ fdate($t->date) }}</td>
                    <td>{{ $t->client?->business_name ?? '—' }}</td>
                    <td>{{ $t->destination ?: '—' }}</td>
                    <td class="num">{{ num($t->quantity, 0) }} <span class="text-xs text-stone-500">{{ $t->unit }}</span></td>
                    <td><x-badge color="sky">{{ $t->type }}</x-badge></td>
                    <td>{{ $t->provider ?: '—' }}</td>
                    <td class="code text-sm">{{ $t->dtv_number ?: '—' }}</td>
                    <td class="text-right">
                        @can('treatments.manage')
                            <a href="{{ route('treatments.edit', $t) }}" class="link text-sm">Corregir</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-empty :colspan="8" message="Todavía no hay tratamientos cargados."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $treatments->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
