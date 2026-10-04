<x-layouts.app title="Valor del dólar">
    <x-page-header title="Valor del dólar" subtitle="El último valor cargado se muestra arriba en todas las pantallas y se propone al facturar en dólares."/>

    @include('treasury._nav')

    @php($types = \App\Services\ExchangeRateService::TYPE_LABELS)

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="panel p-5">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Valor vigente</p>
            @if ($current)
                <p class="mt-2 text-4xl font-bold tabular-nums text-stone-900 dark:text-white">{{ money($current->sell) }}</p>
                <p class="mt-1 text-sm text-stone-500">
                    Vendedor · {{ fdate($current->date) }}{{ $current->source ? ' · '.(\App\Models\ExchangeRate::SOURCES[$current->source] ?? $current->source) : '' }}
                    @if ($current->buy)<br>Comprador {{ money($current->buy) }}@endif
                </p>
                @if (! $current->date->isToday())
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">No hay valor de hoy: se usa el del {{ fdate($current->date) }}.</p>
                @endif
            @else
                <p class="mt-2 text-sm text-stone-500">Todavía no se cargó ningún valor.</p>
            @endif

            <p @class([
                'mt-4 rounded-lg px-3 py-2 text-sm',
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' => $autoEnabled,
                'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-300' => ! $autoEnabled,
            ])>
                @if ($autoEnabled)
                    Se actualiza solo cada {{ \App\Services\ExchangeRateService::AUTO_HOURS }} horas: <strong>{{ $types[$autoType] }}</strong>.
                    @if ($lastFetch)<br>Última consulta: {{ $lastFetch->format('d/m/Y H:i') }}.@endif
                @else
                    Actualización automática apagada.
                @endif
            </p>
        </div>

        @can('exchange.manage')
            <x-panel title="Traer de internet" class="lg:col-span-2">
                <p class="text-sm text-stone-500">Trae el valor de hoy: el oficial es el del Banco Nación; el blue, el del mercado informal.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($types as $type => $label)
                        <form method="POST" action="{{ route('exchange.fetch') }}">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <button class="{{ $type === 'oficial' ? 'btn btn-primary' : 'btn btn-secondary' }}">Traer {{ mb_strtolower($label) }}</button>
                        </form>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('exchange.auto') }}" class="mt-4 grid gap-4 border-t border-stone-200 pt-4 md:grid-cols-3 dark:border-stone-800">
                    @csrf
                    <x-select name="enabled" label="¿Actualizar automáticamente?" :options="['1' => 'Sí, cada '.\App\Services\ExchangeRateService::AUTO_HOURS.' horas', '0' => 'No, lo cargo yo']" :value="$autoEnabled ? '1' : '0'"/>
                    <x-select name="type" label="¿Qué dólar?" :options="$types" :value="$autoType"/>
                    <div class="flex items-end">
                        <button class="btn btn-secondary w-full">Guardar preferencia</button>
                    </div>
                </form>
            </x-panel>
        @endcan
    </div>

    @can('exchange.manage')
        <div class="mb-6">
            <x-panel title="Cargar a mano">
                <form method="POST" action="{{ route('exchange.store') }}" class="grid gap-4 md:grid-cols-4">
                    @csrf
                    <x-input name="date" type="date" label="Fecha" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                    <x-input name="sell" inputmode="decimal" label="Vendedor ($)" required hint="Ej.: 1.234,50"/>
                    <x-input name="buy" inputmode="decimal" label="Comprador ($)"/>
                    <x-select name="source" label="Fuente" :options="\App\Models\ExchangeRate::SOURCES" value="BNA"/>
                    <div class="md:col-span-4 flex items-center justify-between gap-3">
                        <p class="form-hint">Si ya había un valor para esa fecha, se reemplaza (el cambio queda en auditoría). Con la actualización automática activada, el valor de hoy se vuelve a traer en la próxima consulta.</p>
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </x-panel>
        </div>
    @endcan

    <x-table>
        <thead><tr><th>Fecha</th><th class="num">Comprador</th><th class="num">Vendedor</th><th>Fuente</th><th>Cargada por</th></tr></thead>
        <tbody>
            @forelse ($rates as $rate)
                <tr>
                    <td>{{ fdate($rate->date) }}</td>
                    <td class="num">{{ $rate->buy ? money($rate->buy) : '—' }}</td>
                    <td class="num font-medium">{{ money($rate->sell) }}</td>
                    <td>{{ \App\Models\ExchangeRate::SOURCES[$rate->source] ?? $rate->source ?? '—' }}</td>
                    <td class="text-stone-500">{{ $rate->user?->full_name ?? (in_array($rate->source, ['BNA', 'BLUE'], true) ? 'Automático' : '—') }}</td>
                </tr>
            @empty
                <x-empty colspan="5" message="Sin valores cargados."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $rates->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
