<x-layouts.app :title="'Cerrar carga '.$load->number">
    <x-page-header :title="'Cerrar carga '.$load->number" subtitle="Revisá el resumen. Una carga cerrada no se puede modificar libremente." :back="route('loads.builder', $load)"/>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Cajones" :value="num($summary['crates'])" icon="box" :hint="$load->planned_crates ? 'de '.num($load->planned_crates).' previstos' : null"/>
        <x-stat label="Kg" :value="kg($summary['kg'], 1)" icon="scale" color="accent"/>
        <x-stat label="Pallets" :value="num($summary['pallets'])" icon="pallet" color="sky"/>
        <x-stat label="Promedio por cajón" :value="kg($summary['avg_kg'])" icon="chart-bar" color="violet"/>
    </div>

    @if ($load->planned_crates && $summary['crates'] < $load->planned_crates)
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            La carga tiene {{ num($summary['crates']) }} cajones y se previeron {{ num($load->planned_crates) }}. Verificá que esté completa antes de cerrar.
        </div>
    @endif

    <div class="mb-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach (['Variedades' => $summary['by_variety'], 'Tamaños' => $summary['by_size'], 'Propietarios' => $summary['by_owner'], 'Productores' => $summary['by_producer']] as $title => $rows)
            <x-panel :title="$title" :padding="false">
                <table class="table">
                    <thead><tr><th></th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="num">{{ num($row['crates']) }}</td><td class="num">{{ num($row['kg'], 1) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>
        @endforeach
    </div>

    <x-panel title="Destino y transporte" class="mb-6">
        <x-dl :items="[
            'Cliente' => $load->client?->business_name,
            'Destino' => $load->destination?->name,
            'Propietario' => $load->owner?->name,
            'Camión' => $load->truck?->plate,
            'Camionero' => $load->driver?->full_name,
        ]"/>
    </x-panel>

    <form method="POST" action="{{ route('loads.close', $load) }}" class="panel flex flex-wrap items-center justify-between gap-4 p-4" x-data x-confirm="¿Cerrar la carga {{ $load->number }} con {{ $summary['crates'] }} cajones?">
        @csrf
        <input type="hidden" name="version" value="{{ $load->version }}">
        <x-checkbox name="confirm" label="Revisé el resumen y la carga está completa" no-hidden required/>
        <div class="flex gap-2">
            <a href="{{ route('loads.builder', $load) }}" class="btn btn-secondary">Volver al armado</a>
            <button class="btn btn-primary btn-lg" @disabled($summary['crates'] === 0)><x-icon name="lock" class="size-5"/> Cerrar carga</button>
        </div>
    </form>
</x-layouts.app>
