<x-layouts.app title="Cotización del dólar">
    <x-page-header title="Cotización del dólar" subtitle="La última cotización cargada se muestra arriba en todas las pantallas y se propone al facturar en dólares."/>

    @include('treasury._nav')

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="panel p-5">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Cotización vigente</p>
            @if ($current)
                <p class="mt-2 text-4xl font-bold tabular-nums text-stone-900 dark:text-white">{{ money($current->sell) }}</p>
                <p class="mt-1 text-sm text-stone-500">
                    Vendedor · {{ fdate($current->date) }}{{ $current->source ? ' · '.(\App\Models\ExchangeRate::SOURCES[$current->source] ?? $current->source) : '' }}
                    @if ($current->buy)<br>Comprador {{ money($current->buy) }}@endif
                </p>
                @if (! $current->date->isToday())
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">No hay cotización de hoy: se usa la del {{ fdate($current->date) }}.</p>
                @endif
            @else
                <p class="mt-2 text-sm text-stone-500">Todavía no se cargó ninguna cotización.</p>
            @endif
        </div>

        @can('exchange.manage')
            <x-panel title="Cargar cotización" class="lg:col-span-2">
                <form method="POST" action="{{ route('exchange.store') }}" class="grid gap-4 md:grid-cols-4">
                    @csrf
                    <x-input name="date" type="date" label="Fecha" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                    <x-input name="sell" inputmode="decimal" label="Vendedor ($)" required autofocus hint="Ej.: 1.234,50"/>
                    <x-input name="buy" inputmode="decimal" label="Comprador ($)"/>
                    <x-select name="source" label="Fuente" :options="\App\Models\ExchangeRate::SOURCES" value="BNA"/>
                    <div class="md:col-span-4 flex items-center justify-between gap-3">
                        <p class="form-hint">Si ya había una cotización para esa fecha, se reemplaza (el cambio queda en auditoría).</p>
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </x-panel>
        @endcan
    </div>

    <x-table>
        <thead><tr><th>Fecha</th><th class="num">Comprador</th><th class="num">Vendedor</th><th>Fuente</th><th>Cargada por</th></tr></thead>
        <tbody>
            @forelse ($rates as $rate)
                <tr>
                    <td>{{ fdate($rate->date) }}</td>
                    <td class="num">{{ $rate->buy ? money($rate->buy) : '—' }}</td>
                    <td class="num font-medium">{{ money($rate->sell) }}</td>
                    <td>{{ \App\Models\ExchangeRate::SOURCES[$rate->source] ?? $rate->source ?? '—' }}</td>
                    <td class="text-stone-500">{{ $rate->user?->full_name ?? '—' }}</td>
                </tr>
            @empty
                <x-empty colspan="5" message="Sin cotizaciones cargadas."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rates->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
