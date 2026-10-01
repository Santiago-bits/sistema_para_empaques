<x-layouts.app title="Insumos">
    <x-page-header title="Stock de insumos" subtitle="Cajas, etiquetas, film, pallets y demás materiales.">
        <x-slot:actions>
            @can('supplies.manage')
                <a href="{{ route('supplies.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo insumo</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-3">
        <x-stat label="Insumos activos" :value="num($activeCount)" icon="archive"/>
        <x-stat label="Con stock bajo" :value="num($lowCount)" icon="alert" :color="$lowCount ? 'red' : 'stone'" :href="route('supplies.index', ['low' => 1])"/>
        @can('costs.view')
            <x-stat label="Valor del stock" :value="money($stockValue)" icon="currency" color="sky"/>
        @endcan
    </div>

    <x-filters>
        <x-input name="q" label="Buscar" :value="request('q')"/>
        <x-select name="category" label="Categoría" :options="$categories" :value="request('category')" placeholder="Todas"/>
        <x-select name="provider_id" label="Proveedor" :options="$providers" :value="request('provider_id')" placeholder="Todos"/>
        <x-select name="low" label="Stock" :options="['1' => 'Sólo stock bajo']" :value="request('low')" placeholder="Todos"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Código</th><th>Insumo</th><th>Categoría</th><th class="num">Stock</th><th class="num">Mínimo</th><th>Proveedor</th><th></th></tr></thead>
        <tbody>
            @forelse ($supplies as $supply)
                @php $low = (float) $supply->min_stock > 0 && (float) $supply->stock <= (float) $supply->min_stock; @endphp
                <tr>
                    <td class="code">{{ $supply->code }}</td>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $supply->name }}</td>
                    <td>{{ $supply->category ?? '—' }}</td>
                    <td class="num">
                        <span @class(['font-semibold text-red-600 dark:text-red-400' => $low])>{{ num($supply->stock, 2) }} {{ $supply->unit }}</span>
                        @if ($low)<x-badge color="red">Bajo</x-badge>@endif
                    </td>
                    <td class="num text-stone-500">{{ num($supply->min_stock, 2) }}</td>
                    <td>{{ $supply->provider?->name ?? '—' }}</td>
                    <td class="text-right"><a href="{{ route('supplies.show', $supply) }}" class="link">Ver / mover</a></td>
                </tr>
            @empty
                <x-empty colspan="7"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $supplies->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
