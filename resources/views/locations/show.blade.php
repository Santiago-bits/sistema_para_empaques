<x-layouts.app :title="$location->name">
    <x-page-header :title="$location->name" :subtitle="$location->path().' · '.(\App\Models\WarehouseLocation::TYPES[$location->type] ?? $location->type)" :back="route('locations.index')">
        <x-slot:actions>
            @unless ($location->active)<x-badge color="zinc">Inactiva</x-badge>@endunless
            @can('locations.move')
                <a href="{{ route('locations.move.create') }}" class="btn btn-secondary"><x-icon name="truck" class="size-4"/> Mover aquí</a>
            @endcan
            @can('locations.manage')
                <a href="{{ route('locations.create', ['parent_id' => $location->id]) }}" class="btn btn-secondary"><x-icon name="plus" class="size-4"/> Sububicación</a>
                <a href="{{ route('locations.edit', $location) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Pallets" :value="num($stats['total'])" icon="pallet" :hint="$stats['direct'] !== $stats['total'] ? num($stats['direct']).' directos' : null"/>
        <x-stat label="Capacidad" :value="$stats['capacity'] ? num($stats['capacity']) : '—'" icon="box" color="sky"/>
        <x-stat label="Libres" :value="$stats['capacity'] ? num($stats['free']) : '—'" icon="check" color="violet"/>
        <x-stat label="Cajones sueltos" :value="num($stats['crates'])" icon="box" color="accent"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Pallets en esta ubicación" :padding="false">
                <table class="table">
                    <thead><tr><th>Pallet</th><th>Variedad</th><th>Lote</th><th class="num">Cajones</th><th>Posición</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($pallets as $pallet)
                            <tr>
                                <td><a href="{{ route('pallets.show', $pallet) }}" class="code link">{{ $pallet->code }}</a></td>
                                <td>{{ $pallet->variety?->name ?? '—' }}</td>
                                <td class="code">{{ $pallet->lot?->code ?? '—' }}</td>
                                <td class="num">{{ num($pallet->crates_count) }}</td>
                                <td>{{ $pallet->location?->name }}</td>
                                <td><x-status :status="$pallet->status"/></td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="Ubicación vacía."/>
                        @endforelse
                    </tbody>
                </table>
                @if ($pallets->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $pallets->links() }}</div>@endif
            </x-panel>

            @if ($crates->total() > 0)
                <x-panel title="Cajones sueltos (sin pallet)" :padding="false">
                    <table class="table">
                        <thead><tr><th>Cajón</th><th>Variedad</th><th>Tamaño</th><th>Estado</th></tr></thead>
                        <tbody>
                            @foreach ($crates as $crate)
                                <tr>
                                    <td><a href="{{ route('crates.show', $crate) }}" class="code link">{{ $crate->code }}</a></td>
                                    <td>{{ $crate->variety?->name ?? '—' }}</td>
                                    <td>{{ $crate->size?->name ?? '—' }}</td>
                                    <td><x-status :status="$crate->status"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($crates->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $crates->links() }}</div>@endif
                </x-panel>
            @endif
        </div>

        <div class="space-y-6">
            @if ($location->children->isNotEmpty())
                <x-panel title="Sububicaciones" :padding="false">
                    <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                        @foreach ($location->children as $child)
                            @php $c = $occupancy[$child->id] ?? ['total' => 0, 'capacity' => 0]; @endphp
                            <li class="flex items-center justify-between px-4 py-2">
                                <a href="{{ route('locations.show', $child) }}" class="link">{{ $child->name }}</a>
                                <span class="tabular-nums text-stone-500">{{ $c['total'] }}{{ $c['capacity'] ? ' / '.$c['capacity'] : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            <x-panel title="Últimos movimientos" :padding="false">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    @forelse ($movements as $m)
                        <li class="px-4 py-2">
                            <p><span class="code">{{ $m->movable?->code ?? '#'.$m->movable_id }}</span>
                                <span class="text-stone-500">{{ $m->fromLocation?->name ?? '—' }} → {{ $m->toLocation?->name ?? $m->to_label ?? '—' }}</span></p>
                            <p class="text-xs text-stone-500 tabular-nums">{{ fdate($m->moved_at, true) }} · {{ $m->user?->full_name }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-stone-500">Sin movimientos.</li>
                    @endforelse
                </ul>
            </x-panel>
        </div>
    </div>
</x-layouts.app>
