<x-layouts.app title="Rendimiento por quinta">
    <x-page-header title="Rendimiento por quinta" subtitle="Lo que entró de cada productor (bines y kilos) contra lo que salió empacado y lo que se descartó." :back="route('reports.index')"/>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Bines" :value="num($totals['bins'])" icon="archive" color="stone"/>
        <x-stat label="Kg que entraron" :value="kg($totals['kg_received'], 0)" icon="arrow-down" color="sky"/>
        <x-stat label="Bultos empacados" :value="num($totals['packages'])" icon="box"/>
        <x-stat label="Kg empacados" :value="kg($totals['kg_packed'], 0)" icon="arrow-up" color="accent"/>
        <x-stat label="Rendimiento" :value="$totals['yield_pct'] !== null ? pct($totals['yield_pct']) : '—'" icon="chart-line" color="violet"/>
    </div>

    <x-filters>
        <x-select name="producer_id" label="Quinta / productor" :options="$producers" :value="request('producer_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="$from"/>
        <x-input name="to" type="date" label="Hasta" :value="$to"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Quinta / productor</th><th class="num">Lotes</th><th class="num">Bines</th><th class="num">Kg que entraron</th><th class="num">Bultos</th><th class="num">Kg empacados</th><th class="num">Kg descartados</th><th class="num">Rendimiento</th><th class="num">Merma</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="font-medium">{{ $r->producer }}</td>
                    <td class="num">{{ num($r->lots) }}</td>
                    <td class="num">{{ num($r->bins) }}</td>
                    <td class="num">{{ num($r->kg_received, 0) }}</td>
                    <td class="num">{{ num($r->packages) }}</td>
                    <td class="num">{{ num($r->kg_packed, 0) }}</td>
                    <td class="num">{{ num($r->kg_rejected, 0) }}</td>
                    <td class="num font-semibold">{{ $r->yield_pct !== null ? pct($r->yield_pct) : '—' }}</td>
                    <td class="num">{{ $r->waste_pct !== null ? pct($r->waste_pct) : '—' }}</td>
                </tr>
            @empty
                <x-empty :colspan="9" message="No hay ingresos de fruta (lotes) en esas fechas."/>
            @endforelse
        </tbody>
    </x-table>
    <p class="mt-3 text-sm text-stone-500">El rendimiento es kilos empacados ÷ kilos que entraron. Para que se calcule, cargá los kilos en cada ingreso de fruta (lote).</p>
</x-layouts.app>
