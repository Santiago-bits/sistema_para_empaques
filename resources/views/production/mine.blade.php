<x-layouts.app title="Mi producción">
    @if (! $packer)
        <x-page-header title="Mi producción"/>
        <div class="panel p-8 text-center text-stone-500">Tu usuario no está vinculado a un embalador. Pedile al administrador que lo vincule.</div>
    @else
        <x-page-header :title="'Mi producción · '.$packer->full_name" :subtitle="'Código '.$packer->code"/>

        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Cajones hoy" :value="num($today->crates)" icon="box"/>
            <x-stat label="Kg hoy" :value="kg($today->kg, 1)" icon="scale" color="accent"/>
            <x-stat label="Cajones del período" :value="num($totals->crates)" icon="box" color="sky"/>
            <x-stat label="Kg del período" :value="kg($totals->kg, 1)" icon="scale" color="violet"/>
        </div>

        <form method="GET" class="panel mb-6 flex flex-wrap items-end gap-3 p-4" data-allow-resubmit>
            <x-input name="date_from" type="date" label="Desde" :value="$from->toDateString()"/>
            <x-input name="date_to" type="date" label="Hasta" :value="$to->toDateString()"/>
            <button class="btn btn-primary">Ver</button>
        </form>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <x-panel title="Por variedad" :padding="false">
                <table class="table">
                    <thead><tr><th>Variedad</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @forelse ($byVariety as $row)
                            <tr><td>{{ $row->label }}</td><td class="num">{{ num($row->crates) }}</td><td class="num">{{ num($row->kg, 1) }}</td></tr>
                        @empty
                            <x-empty colspan="3" message="Sin producción en el período."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
            <x-panel title="Por tamaño" :padding="false">
                <table class="table">
                    <thead><tr><th>Tamaño</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @forelse ($bySize as $row)
                            <tr><td>{{ $row->label }}</td><td class="num">{{ num($row->crates) }}</td><td class="num">{{ num($row->kg, 1) }}</td></tr>
                        @empty
                            <x-empty colspan="3" message="Sin producción en el período."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        </div>

        <x-table>
            <thead><tr><th>Fecha y hora</th><th>Cajón</th><th>Variedad</th><th>Tamaño</th><th class="num">Peso</th><th>Turno</th></tr></thead>
            <tbody>
                @forelse ($history as $record)
                    <tr @class(['opacity-50 line-through' => $record->voided_at])>
                        <td class="tabular-nums">{{ fdate($record->recorded_at, true) }}</td>
                        <td class="code">{{ $record->crate?->code }}</td>
                        <td>{{ $record->variety?->name }}</td>
                        <td>{{ $record->size?->name }}</td>
                        <td class="num">{{ num($record->weight, 2) }}</td>
                        <td>{{ $record->shift?->name }}</td>
                    </tr>
                @empty
                    <x-empty colspan="6"/>
                @endforelse
            </tbody>
            <x-slot:footer>{{ $history->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
