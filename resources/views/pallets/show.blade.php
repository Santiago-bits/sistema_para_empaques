<x-layouts.app :title="'Pallet '.$pallet->code">
    <x-page-header :title="'Pallet '.$pallet->code" :subtitle="'Ingreso '.fdate($pallet->received_at, true)" :back="route('pallets.index')">
        <x-slot:actions>
            <x-status :status="$pallet->status" class="text-sm"/>
            @can('labels.print')
                <a href="{{ route('labels.pallets', ['ids' => [$pallet->id]]) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Etiqueta</a>
                <a href="{{ route('labels.crates', ['pallet_id' => $pallet->id]) }}" target="_blank" class="btn btn-secondary"><x-icon name="printer" class="size-4"/> Etiquetas de cajones</a>
            @endcan
            @can('traceability.view')
                <a href="{{ route('traceability.index', ['code' => $pallet->code]) }}" class="btn btn-secondary"><x-icon name="route" class="size-4"/> Trazabilidad</a>
            @endcan
            @if ($pallet->status->value !== 'voided')
                @can('crates.create')
                    <a href="{{ route('crates.create', ['pallet_id' => $pallet->id]) }}" class="btn btn-secondary"><x-icon name="plus" class="size-4"/> Cajón</a>
                @endcan
                @can('pallets.void')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'void-pallet')">Anular</button>
                @endcan
                @can('pallets.update')
                    <a href="{{ route('pallets.edit', $pallet) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Cajones" :value="num($totals->crates)" icon="box"/>
        <x-stat label="Kg procesados" :value="kg($totals->kg, 1)" icon="scale" color="accent"/>
        <x-stat label="Peso bruto" :value="$pallet->gross_weight ? kg($pallet->gross_weight, 1) : '—'" icon="pallet" color="sky"/>
        <x-stat label="Ubicación" :value="$pallet->location?->name ?? 'Sin ubicar'" icon="pin" color="violet"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos">
                <x-dl :items="[
                    'Código de barras' => $pallet->barcode,
                    'Lote' => $pallet->lot?->code,
                    'Productor' => $pallet->producer?->name,
                    'Propietario' => $pallet->owner?->name,
                    'Variedad' => $pallet->variety?->name,
                    'Procedencia' => $pallet->origin,
                    'Cantidad declarada' => num($pallet->quantity),
                    'Temporada' => $pallet->season?->name,
                    'Ingresado por' => $pallet->creator?->full_name,
                    'Observaciones' => $pallet->notes,
                ]"/>
            </x-panel>

            <x-panel title="Cajones del pallet" :padding="false">
                <table class="table">
                    <thead><tr><th>Código</th><th>Variedad</th><th>Tamaño</th><th>Embalador</th><th class="num">Peso</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($crates as $crate)
                            <tr>
                                <td><a href="{{ route('crates.show', $crate) }}" class="code link">{{ $crate->code }}</a></td>
                                <td>{{ $crate->variety?->name ?? '—' }}</td>
                                <td>{{ $crate->size?->name ?? '—' }}</td>
                                <td>{{ $crate->packer ? $crate->packer->code.' — '.$crate->packer->full_name : '—' }}</td>
                                <td class="num">{{ $crate->weight !== null ? num($crate->weight, 2) : '—' }}</td>
                                <td><x-status :status="$crate->status"/></td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="Todavía no hay cajones en este pallet."/>
                        @endforelse
                    </tbody>
                </table>
                @if ($crates->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $crates->links() }}</div>@endif
            </x-panel>

            @if ($pallet->movements->isNotEmpty())
                <x-panel title="Movimientos de ubicación" :padding="false">
                    <table class="table">
                        <thead><tr><th>Fecha</th><th>Desde</th><th>Hacia</th><th>Usuario</th><th>Nota</th></tr></thead>
                        <tbody>
                            @foreach ($pallet->movements as $movement)
                                <tr>
                                    <td class="tabular-nums">{{ fdate($movement->moved_at, true) }}</td>
                                    <td>{{ $movement->fromLocation?->name ?? '—' }}</td>
                                    <td>{{ $movement->toLocation?->name ?? $movement->to_label ?? '—' }}</td>
                                    <td>{{ $movement->user?->full_name }}</td>
                                    <td class="text-stone-500">{{ $movement->notes }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-panel>
            @endif
        </div>

        <x-panel title="Historial de estados">
            <x-timeline :events="$pallet->stateHistories->map(fn ($h) => [
                'time' => $h->created_at,
                'title' => \App\Enums\PalletStatus::tryFrom($h->to_state)?->label() ?? $h->to_state,
                'detail' => $h->notes,
                'user' => $h->user?->full_name,
                'color' => $h->to_state === 'voided' ? 'red' : 'brand',
            ])->all()"/>
        </x-panel>
    </div>

    @can('pallets.void')
        <x-modal name="void-pallet" title="Anular pallet {{ $pallet->code }}">
            <form method="POST" action="{{ route('pallets.void', $pallet) }}" class="space-y-4" x-data x-confirm="¿Confirmás la anulación del pallet?">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'void-pallet')">Cancelar</button>
                    <button class="btn btn-danger">Anular pallet</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
