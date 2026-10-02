@php
    $icons = ['production' => 'chart-bar', 'packers' => 'users', 'packer' => 'user-chart', 'varieties' => 'layers', 'sizes' => 'box',
        'producers' => 'briefcase', 'loads' => 'truck', 'waste' => 'trash', 'executive' => 'chart-line'];
    $descriptions = [
        'production' => 'Por día, semana, mes o año. Detalle de cada registro.',
        'packers' => 'Cajones, kg, kg/hora y merma de cada embalador.',
        'packer' => 'Informe individual exportable de un embalador.',
        'varieties' => 'Cantidades, kg y participación por variedad.',
        'sizes' => 'Cantidades y kg por tamaño.',
        'producers' => 'Producción y calidad por productor.',
        'loads' => 'Cargas, kg, destinos y camiones.',
        'waste' => 'Rechazos y merma por motivo, variedad, lote.',
        'executive' => 'Producción, merma, despachos, costos, facturación y rentabilidad.',
    ];
@endphp
<x-layouts.app title="Reportes">
    <x-page-header title="Reportes" subtitle="Todos los reportes se filtran y se exportan a Excel, CSV o PDF exactamente como se ven.">
        <x-slot:actions>
            @can('stats.view')
                <a href="{{ route('stats.index') }}" class="btn btn-secondary"><x-icon name="chart-line" class="size-4"/> Estadísticas</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Kg procesados (30 días)" :value="kg($indicators['kg_processed'], 0)" icon="scale"/>
        <x-stat label="Cajones" :value="num($indicators['crates_processed'])" icon="box" color="sky"/>
        <x-stat label="Kg / hora" :value="num($indicators['kg_per_hour'], 1)" icon="clock" color="violet"/>
        <x-stat label="Kg prom. / cajón" :value="num($indicators['avg_kg_per_crate'], 2)" icon="chart-bar" color="accent"/>
        <x-stat label="% merma" :value="pct($indicators['waste_pct'])" icon="trash" color="red"/>
        <x-stat label="Kg despachados" :value="kg($indicators['kg_dispatched'], 0)" icon="truck" color="stone"/>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => $title)
            <a href="{{ route('reports.show', $key) }}" class="panel group flex items-start gap-3 p-4 transition hover:border-brand-400 hover:shadow">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-600/10 text-brand-700 dark:text-brand-400"><x-icon :name="$icons[$key] ?? 'chart-bar'" class="size-5"/></span>
                <span>
                    <span class="block font-medium text-stone-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{{ $title }}</span>
                    <span class="text-xs text-stone-500">{{ $descriptions[$key] ?? '' }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
