<x-layouts.app title="Rendimiento de cera e insumos">
    <x-page-header title="Rendimiento de cera e insumos" subtitle="Cuántos bultos rindió cada tambor de cera (u otro insumo): desde que se abrió hasta que se terminó.">
        <x-slot:actions>
            @can('yields.manage')
                <a href="{{ route('yields.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo tambor / insumo</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <thead><tr><th>Insumo</th><th>Desde</th><th>Hasta</th><th class="num">Bultos</th><th class="num">Cantidad usada</th><th class="num">Bultos por unidad</th><th></th></tr></thead>
        <tbody>
            @forelse ($yields as $y)
                <tr>
                    <td>
                        <span class="font-medium">{{ $y->name }}</span>
                        @if ($y->supply)<span class="block text-xs text-stone-500">{{ $y->supply->name }}</span>@endif
                    </td>
                    <td class="whitespace-nowrap tabular-nums">{{ fdate($y->started_on) }}</td>
                    <td class="whitespace-nowrap tabular-nums">{!! $y->ended_on ? e(fdate($y->ended_on)) : '<span class="text-emerald-700 dark:text-emerald-400">En uso</span>' !!}</td>
                    <td class="num font-semibold">{{ num($y->packages()) }}</td>
                    <td class="num">{{ $y->quantity_used !== null ? num($y->quantity_used, 2).' '.$y->unit : '—' }}</td>
                    <td class="num">{{ $y->packagesPerUnit() !== null ? num($y->packagesPerUnit(), 1).' por '.($y->unit ?: 'unidad') : '—' }}</td>
                    <td class="text-right">
                        @can('yields.manage')
                            <a href="{{ route('yields.edit', $y) }}" class="link text-sm">{{ $y->isOpen() ? 'Terminar / corregir' : 'Corregir' }}</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-empty :colspan="7" message="Todavía no hay registros. Cuando abras un tambor de cera, cargalo acá con la fecha de inicio."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $yields->links() }}</x-slot:footer>
    </x-table>
    <p class="mt-3 text-sm text-stone-500">Los bultos se cuentan solos con la producción registrada entre las dos fechas. Si preferís, podés cargarlos a mano.</p>
</x-layouts.app>
