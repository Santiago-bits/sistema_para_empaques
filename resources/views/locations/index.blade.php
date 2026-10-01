<x-layouts.app title="Ubicaciones">
    <x-page-header title="Ubicaciones e inventario físico" subtitle="Galpón → sectores → pasillos → estanterías → posiciones, con ocupación en tiempo real.">
        <x-slot:actions>
            <a href="{{ route('locations.map') }}" class="btn btn-secondary"><x-icon name="map" class="size-4"/> Mapa</a>
            <a href="{{ route('locations.movements') }}" class="btn btn-secondary"><x-icon name="route" class="size-4"/> Movimientos</a>
            @can('locations.move')
                <a href="{{ route('locations.move.create') }}" class="btn btn-secondary"><x-icon name="truck" class="size-4"/> Mover</a>
            @endcan
            @can('locations.manage')
                <a href="{{ route('locations.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva ubicación</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Capacidad del galpón" :value="num($capacity['capacity']).' pallets'" icon="pallet"/>
        <x-stat label="Pallets en el galpón" :value="num($capacity['pallets'])" icon="box" color="accent" :hint="$capacity['unlocated'] ? num($capacity['unlocated']).' sin ubicar' : null"/>
        <x-stat label="Espacios libres" :value="num($capacity['free'])" icon="check" color="sky"/>
        <x-stat label="Ocupación" :value="pct($capacity['occupancy_pct'])" icon="chart-bar" color="violet"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
        <x-panel :title="'Árbol de ubicaciones ('.$total.')'">
            <x-slot:actions>
                <form method="GET" class="flex items-center gap-2" data-allow-resubmit>
                    <input name="q" value="{{ request('q') }}" placeholder="Buscar…" class="form-input py-1.5 text-sm">
                    <label class="flex items-center gap-1 text-xs text-stone-500"><input type="checkbox" name="inactive" value="1" @checked(request('inactive')) onchange="this.form.submit()"> Inactivas</label>
                </form>
            </x-slot:actions>
            @if ($tree->get(0, collect())->isEmpty())
                <p class="py-8 text-center text-sm text-stone-500">No hay ubicaciones cargadas. Empezá creando los sectores del galpón.</p>
            @else
                <ul class="text-sm">
                    @foreach ($tree->get(0) as $node)
                        @include('locations._node', ['node' => $node, 'depth' => 0])
                    @endforeach
                </ul>
            @endif
        </x-panel>

        <x-panel title="Pallets por estado">
            <ul class="space-y-2 text-sm">
                @foreach ($inventory as $status => $item)
                    <li class="flex items-center justify-between">
                        <x-badge :color="$item['color']">{{ $item['label'] }}</x-badge>
                        <span class="font-semibold tabular-nums">{{ num($item['count']) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    </div>
</x-layouts.app>
