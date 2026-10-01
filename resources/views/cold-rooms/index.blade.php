<x-layouts.app title="Cámaras frigoríficas">
    <x-page-header title="Cámaras frigoríficas" subtitle="Temperatura y humedad con alertas fuera de rango.">
        <x-slot:actions>
            @can('cold_rooms.manage')
                <a href="{{ route('cold-rooms.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva cámara</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-3 gap-3">
        <x-stat label="Cámaras activas" :value="num($summary['rooms'])" icon="snow" color="sky"/>
        <x-stat label="Fuera de rango" :value="num($summary['out_of_range'])" icon="alert" :color="$summary['out_of_range'] ? 'red' : 'stone'"/>
        <x-stat label="Sin lecturas (6 h)" :value="num($summary['stale'])" icon="clock" :color="$summary['stale'] ? 'amber' : 'stone'"/>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($rooms as $room)
            @php $last = $latest->get($room->id); @endphp
            <a href="{{ route('cold-rooms.show', $room) }}" @class(['panel block p-5 transition hover:shadow', 'border-red-400 dark:border-red-700' => $last?->out_of_range])>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-stone-900 dark:text-white">{{ $room->name }}</p>
                        <p class="text-xs text-stone-500">{{ $room->code }} · rango {{ num($room->temp_min, 1) }} a {{ num($room->temp_max, 1) }} °C</p>
                    </div>
                    @unless ($room->active)<x-badge color="zinc">Inactiva</x-badge>@endunless
                </div>
                @if ($last)
                    <p @class(['mt-4 text-4xl font-bold tabular-nums', 'text-red-600 dark:text-red-400' => $last->out_of_range, 'text-sky-700 dark:text-sky-300' => ! $last->out_of_range])>
                        {{ num($last->temperature, 1) }} °C
                    </p>
                    <p class="text-sm text-stone-500">
                        @if ($last->humidity !== null) Humedad {{ num($last->humidity, 0) }} % · @endif
                        {{ $last->recorded_at->diffForHumans() }}
                    </p>
                    @if ($last->out_of_range)<x-badge color="red" class="mt-2">FUERA DE RANGO</x-badge>@endif
                @else
                    <p class="mt-4 text-sm text-stone-500">Sin lecturas registradas.</p>
                @endif
            </a>
        @empty
            <div class="panel p-8 text-center text-sm text-stone-500 md:col-span-2 xl:col-span-3">No hay cámaras cargadas.</div>
        @endforelse
    </div>
</x-layouts.app>
