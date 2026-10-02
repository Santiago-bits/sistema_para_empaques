@php
    $categories = \App\Models\Cost::CATEGORIES;
    $grandTotal = array_sum($totals);
@endphp
<x-layouts.app title="Costos">
    <x-page-header title="Costos" :subtitle="$filters['from']->format('d/m/Y').' al '.$filters['to']->format('d/m/Y')">
        <x-slot:actions>
            @can('costs.manage')
                <a href="{{ route('costs.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo costo</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="from" type="date" label="Desde" :value="$filters['from']->toDateString()"/>
        <x-input name="to" type="date" label="Hasta" :value="$filters['to']->toDateString()"/>
        <x-select name="category" label="Categoría" :options="$categories" :value="$filters['category']" placeholder="Todas"/>
        <x-input name="q" label="Descripción" :value="$filters['q']"/>
    </x-filters>

    @if ($profit)
        <x-panel title="Rentabilidad del período" class="mb-6">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <x-stat label="Ingresos facturados" :value="money($profit['revenue'])" icon="receipt" color="sky" :hint="$profit['invoices'].' comprobantes autorizados'"/>
                <x-stat label="Costos" :value="money($profit['costs'])" icon="currency" color="red"/>
                <x-stat label="Resultado" :value="money($profit['profit'])" icon="chart-line" :color="$profit['profit'] >= 0 ? 'brand' : 'red'" :hint="$profit['margin_pct'] !== null ? 'Margen '.pct($profit['margin_pct']) : null"/>
                <x-stat label="Costo por kg procesado" :value="$profit['cost_per_kg'] !== null ? money($profit['cost_per_kg']) : '—'" icon="scale" color="stone" :hint="$profit['revenue_per_kg'] !== null ? 'Ingreso por kg '.money($profit['revenue_per_kg']) : kg($profit['kg'], 0).' procesados'"/>
            </div>
            <p class="form-hint mt-3">Ingresos: comprobantes autorizados por ARCA en el período, en pesos según su cotización; las notas de crédito restan.</p>
        </x-panel>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Total del período" :value="money($grandTotal)" icon="currency"/>
        @foreach ($categories as $key => $label)
            <x-stat :label="$label" :value="money($totals[$key] ?? 0)" color="stone" :href="route('costs.index', array_merge(request()->except('page'), ['category' => $key]))"/>
        @endforeach
    </div>

    <x-table>
        <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Carga</th><th class="num">Importe</th><th>Cargado por</th><th></th></tr></thead>
        <tbody>
            @forelse ($costs as $cost)
                <tr>
                    <td>{{ fdate($cost->date) }}</td>
                    <td><x-badge color="stone">{{ $categories[$cost->category] ?? $cost->category }}</x-badge></td>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $cost->description }}</td>
                    <td>
                        @if ($cost->costable instanceof \App\Models\Load)
                            @can('loads.view')<a href="{{ route('loads.show', $cost->costable) }}" class="link code">{{ $cost->costable->number }}</a>@else<span class="code">{{ $cost->costable->number }}</span>@endcan
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">{{ money($cost->amount) }}</td>
                    <td class="text-stone-500">{{ $cost->user?->full_name ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @can('costs.manage')
                            <a href="{{ route('costs.edit', $cost) }}" class="link">Editar</a>
                            <form method="POST" action="{{ route('costs.destroy', $cost) }}" class="inline" x-data x-confirm="¿Eliminar el costo «{{ $cost->description }}»?">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 text-sm text-red-600 hover:underline dark:text-red-400">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay costos en el período."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $costs->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
