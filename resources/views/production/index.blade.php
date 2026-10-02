<x-layouts.app title="Registros de producción">
    <x-page-header title="Registros de producción" subtitle="Quién procesó cada cajón, cuándo, con qué peso y en qué línea.">
        <x-slot:actions>
            @can('production.scan')
                <a href="{{ route('production.scan') }}" class="btn btn-primary"><x-icon name="scan" class="size-4"/> Modo escaneo</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="date_from" type="date" label="Desde" :value="request('date_from')"/>
        <x-input name="date_to" type="date" label="Hasta" :value="request('date_to')"/>
        <x-input name="crate" label="Cajón" :value="request('crate')" class="code"/>
        <x-select name="packer_id" label="Embalador" :options="$packers" :value="request('packer_id')" placeholder="Todos"/>
        <x-select name="variety_id" label="Variedad" :options="$varieties" :value="request('variety_id')" placeholder="Todas"/>
        <x-select name="size_id" label="Tamaño" :options="$sizes" :value="request('size_id')" placeholder="Todos"/>
        <x-select name="shift_id" label="Turno" :options="$shifts" :value="request('shift_id')" placeholder="Todos"/>
        <x-select name="production_line_id" label="Línea" :options="$lines" :value="request('production_line_id')" placeholder="Todas"/>
        <x-select name="user_id" label="Operador" :options="$users" :value="request('user_id')" placeholder="Todos"/>
        <x-select name="state" label="Estado" :options="['valid' => 'Válidos', 'voided' => 'Anulados', 'authorized' => 'Con autorización']" :value="request('state')" placeholder="Todos"/>
    </x-filters>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Cajones válidos" :value="num($totals->crates)" icon="box"/>
        <x-stat label="Kg válidos" :value="kg($totals->kg, 1)" icon="scale" color="accent"/>
        <x-stat label="Promedio por cajón" :value="$totals->crates ? kg($totals->kg / $totals->crates, 2) : '—'" icon="chart-bar" color="sky"/>
        <x-stat label="Pesos autorizados" :value="num($totals->authorized)" icon="shield" color="amber"/>
    </div>

    <x-table>
        <thead><tr><th>Fecha y hora</th><th>Cajón</th><th>Embalador</th><th>Variedad</th><th>Tamaño</th><th class="num">Peso</th><th>Turno / línea</th><th>Operador</th><th></th></tr></thead>
        <tbody>
            @forelse ($records as $record)
                <tr @class(['opacity-50' => $record->voided_at])>
                    <td class="tabular-nums whitespace-nowrap">{{ fdate($record->recorded_at, true) }}</td>
                    <td>
                        @if ($record->crate)<a href="{{ route('crates.show', $record->crate) }}" class="code link">{{ $record->crate->code }}</a>@endif
                        @if ($record->voided_at)<x-badge color="red">Anulado</x-badge>@endif
                    </td>
                    <td>{{ $record->packer?->code }} <span class="text-stone-500">{{ $record->packer?->full_name }}</span></td>
                    <td>{{ $record->variety?->name }}</td>
                    <td>{{ $record->size?->name }}</td>
                    <td class="num whitespace-nowrap">
                        {{ num($record->weight, 2) }}
                        @if ($record->authorized_by)<span title="Autorizado por {{ $record->authorizer?->full_name }}: {{ $record->authorization_reason }}" class="text-amber-600">●</span>@endif
                    </td>
                    <td class="text-stone-500">{{ $record->shift?->name }}{{ $record->productionLine ? ' · '.$record->productionLine->name : '' }}</td>
                    <td>{{ $record->user?->full_name }}</td>
                    <td class="text-right">
                        @if (! $record->voided_at)
                            @can('crates.update')
                                @if ($record->crate)
                                    <a href="{{ route('crates.edit', $record->crate) }}" class="mr-3 text-sm link" title="Corregir peso, variedad, tamaño o embalador">Corregir</a>
                                @endif
                            @endcan
                            @can('production.void')
                                <button type="button" class="text-sm text-red-600 hover:underline"
                                        @click="$dispatch('void-record', {{ \Illuminate\Support\Js::from(['url' => route('production.void', $record), 'crate' => $record->crate?->code]) }})">Anular</button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="9"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $records->links() }}</x-slot:footer>
    </x-table>

    @can('production.void')
        <div x-data="{ open: false, url: '', crate: '' }" @void-record.window="open = true; url = $event.detail.url; crate = $event.detail.crate" x-show="open" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="open = false">
            <form method="POST" :action="url" class="panel w-full max-w-md space-y-4 p-5" x-data x-confirm="¿Anular el registro? El cajón quedará disponible para registrarse de nuevo.">
                @csrf
                <h3 class="font-semibold">Anular registro del cajón <span class="code" x-text="crate"></span></h3>
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="open = false">Cancelar</button>
                    <button class="btn btn-danger">Anular registro</button>
                </div>
            </form>
        </div>
    @endcan
</x-layouts.app>
