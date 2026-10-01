<x-layouts.app title="Movimientos">
    <x-page-header title="Informe de movimientos" subtitle="Quién movió qué, desde dónde, hacia dónde y cuándo." :back="route('locations.index')"/>

    <x-filters>
        <x-input name="code" label="Pallet o cajón" :value="$code" class="code"/>
        <x-select name="type" label="Tipo" :options="['pallet' => 'Pallets', 'crate' => 'Cajones']" :value="request('type')" placeholder="Todos"/>
        <x-select name="location_id" label="Ubicación" :options="$locations" :value="request('location_id')" placeholder="Todas"/>
        <x-select name="user_id" label="Usuario" :options="$users" :value="request('user_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    @if ($journey !== null)
        <x-panel :title="'Recorrido de '.($target['type'] === 'pallet' ? 'pallet ' : 'cajón ').$target['model']->code" class="mb-6">
            @if ($journey->isEmpty())
                <p class="text-sm text-stone-500">Sin movimientos registrados.</p>
            @else
                <ol class="flex flex-wrap items-center gap-2 text-sm">
                    @foreach ($journey as $step)
                        <li class="rounded-lg border border-stone-200 px-3 py-2 dark:border-stone-700">
                            <span class="block text-xs tabular-nums text-stone-500">{{ fdate($step->moved_at, true) }}</span>
                            <span class="font-medium">{{ $step->toLocation?->name ?? $step->to_label ?? '—' }}</span>
                        </li>
                        @unless ($loop->last)<x-icon name="chevron-right" class="size-4 text-stone-400"/>@endunless
                    @endforeach
                </ol>
            @endif
        </x-panel>
    @elseif ($code !== '')
        <div class="panel mb-6 p-4 text-sm text-stone-500">No se encontró el pallet o cajón <span class="code">{{ $code }}</span>.</div>
    @endif

    <x-table>
        <thead><tr><th>Fecha y hora</th><th>Qué</th><th>Desde</th><th>Hacia</th><th>Quién</th><th>Nota</th></tr></thead>
        <tbody>
            @forelse ($movements as $m)
                <tr>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($m->moved_at, true) }}</td>
                    <td>{{ $m->movable_type === 'pallet' ? 'Pallet' : 'Cajón' }} <span class="code">{{ $m->movable?->code ?? '#'.$m->movable_id }}</span></td>
                    <td>{{ $m->fromLocation?->name ?? '—' }}</td>
                    <td>{{ $m->toLocation?->name ?? $m->to_label ?? '—' }}</td>
                    <td>{{ $m->user?->full_name }}</td>
                    <td class="text-stone-500">{{ $m->notes }}</td>
                </tr>
            @empty
                <x-empty colspan="6"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $movements->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
