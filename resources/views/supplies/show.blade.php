<x-layouts.app :title="$supply->name">
    <x-page-header :title="$supply->name" :subtitle="$supply->code.' · '.($supply->category ?? 'Sin categoría')" :back="route('supplies.index')">
        <x-slot:actions>
            @can('supplies.manage')
                <a href="{{ route('supplies.edit', $supply) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Stock actual" :value="num($supply->stock, 2).' '.$supply->unit" icon="archive" :color="$low ? 'red' : 'brand'" :hint="$low ? 'Debajo del mínimo' : null"/>
        <x-stat label="Stock mínimo" :value="num($supply->min_stock, 2)" icon="alert" color="amber"/>
        <x-stat label="Proveedor" :value="$supply->provider?->name ?? '—'" icon="truck" color="sky"/>
        @can('costs.view')
            <x-stat label="Costo unitario" :value="$supply->unit_cost !== null ? money($supply->unit_cost) : '—'" icon="currency" color="violet"/>
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @can('supplies.manage')
            <x-panel title="Registrar movimiento">
                <form method="POST" action="{{ route('supplies.movements.store', $supply) }}" class="space-y-4" x-data="{ type: 'in' }">
                    @csrf
                    <x-select name="type" label="Tipo" :options="$types" value="in" x-model="type" required/>
                    <x-input name="quantity" label="Cantidad" inputmode="decimal" required
                             hint="En «Ajuste» se indica el stock real contado (queda el valor informado)."/>
                    <div x-show="type === 'in'" class="space-y-4">
                        <x-input name="unit_cost" label="Costo unitario" inputmode="decimal"/>
                        <x-select name="provider_id" label="Proveedor" :options="$providers" :value="$supply->provider_id" placeholder="—"/>
                    </div>
                    <x-input name="reference" label="Referencia" hint="Remito o factura del proveedor, orden interna…"/>
                    <x-input name="notes" label="Observación"/>
                    <button class="btn btn-primary w-full">Registrar</button>
                </form>
            </x-panel>
        @endcan

        <x-panel title="Movimientos" :padding="false" class="lg:col-span-2">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th class="num">Cantidad</th><th class="num">Stock</th><th>Referencia</th><th>Usuario</th></tr></thead>
                <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td class="tabular-nums whitespace-nowrap">{{ fdate($m->moved_at, true) }}</td>
                            <td><x-badge :color="['in' => 'emerald', 'out' => 'red', 'adjust' => 'amber'][$m->type] ?? 'stone'">{{ $types[$m->type] ?? $m->type }}</x-badge></td>
                            <td class="num">{{ $m->type === 'out' ? '−' : ($m->type === 'in' ? '+' : '') }}{{ num($m->quantity, 2) }}</td>
                            <td class="num">{{ num($m->stock_after, 2) }}</td>
                            <td class="text-stone-500">{{ $m->reference }} {{ $m->notes ? '· '.$m->notes : '' }}</td>
                            <td>{{ $m->user?->full_name }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="6" message="Sin movimientos."/>
                    @endforelse
                </tbody>
            </table>
            @if ($movements->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $movements->links() }}</div>@endif
        </x-panel>
    </div>
</x-layouts.app>
