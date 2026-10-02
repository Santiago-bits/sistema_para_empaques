<x-layouts.app :title="'Lote '.$lot->code">
    <x-page-header :title="'Lote '.$lot->code" :subtitle="$lot->producer?->name.' · '.fdate($lot->date)" :back="route('lots.index')">
        <x-slot:actions>
            <x-badge :color="['open' => 'emerald', 'closed' => 'blue', 'voided' => 'zinc'][$lot->status] ?? 'stone'" class="text-sm">{{ \App\Models\Lot::STATUSES[$lot->status] ?? $lot->status }}</x-badge>
            @can('lots.manage')
                @if ($lot->status === 'open')
                    <form method="POST" action="{{ route('lots.close', $lot) }}" x-data x-confirm="¿Cerrar el lote {{ $lot->code }}?">
                        @csrf
                        <button class="btn btn-secondary"><x-icon name="lock" class="size-4"/> Cerrar lote</button>
                    </form>
                @endif
                @if ($lot->status !== 'voided')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'void-lot')">Anular</button>
                    <a href="{{ route('lots.edit', $lot) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Pallets" :value="num($summary['pallets'])" icon="pallet"/>
        <x-stat label="Cajones" :value="num($summary['crates'])" icon="box" color="sky"/>
        <x-stat label="Procesados" :value="num($summary['records'])" icon="check-badge" color="violet"/>
        <x-stat label="Kg procesados" :value="kg($summary['processed_kg'], 0)" icon="scale" color="accent"/>
        <x-stat label="Rechazos" :value="num($summary['rejects']).' · '.kg($summary['rejected_kg'], 0)" icon="trash" color="red"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos del lote">
                <x-dl :items="[
                    'Productor' => $lot->producer?->name,
                    'Propietario' => $lot->owner?->name,
                    'Variedad' => $lot->variety?->name,
                    'Temporada' => $lot->season?->name,
                    'Origen' => $lot->origin,
                    'Campo' => $lot->field,
                    'Cantidad declarada' => num($lot->quantity),
                    'Envase' => $lot->containerType?->name,
                    'Creado por' => $lot->creator?->full_name,
                    'Observaciones' => $lot->notes,
                ]"/>
            </x-panel>

            @if (module_enabled('treasury') && (auth()->user()->can('treasury.view') || auth()->user()->can('lots.settle')))
                @php
                    $purchase = $lot->purchaseAmount();
                    $feePerKg = (float) setting('treasury.association_fee_per_kg', 0);
                    $fee = $lot->kg_received !== null && $feePerKg > 0 ? round((float) $lot->kg_received * $feePerKg, 2) : 0;
                @endphp
                <x-panel title="Compra de fruta al productor">
                    @if ($purchase === null)
                        <p class="text-sm text-stone-500">Cargá los kilos recibidos y el precio por kilo (Editar) para liquidar el lote al productor.</p>
                    @else
                        <x-dl :items="[
                            'Kilos recibidos' => kg($lot->kg_received),
                            'Precio por kilo' => money($lot->price_per_kg),
                            'Importe de compra' => money($purchase),
                            'Tasa de asociación' => $fee > 0 ? '− '.money($fee).' ('.money($feePerKg).' por kg)' : null,
                            'Neto a favor del productor' => money($purchase - $fee),
                            'Estado' => $lot->settled_at ? 'Liquidado el '.fdate($lot->settled_at, true) : 'Sin liquidar',
                        ]"/>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if (! $lot->settled_at && $lot->status !== 'voided')
                                @can('lots.settle')
                                    <form method="POST" action="{{ route('lots.settle', $lot) }}" x-data x-confirm="¿Liquidar {{ money($purchase - $fee) }} a favor de {{ $lot->producer?->name }}?">
                                        @csrf
                                        <button class="btn btn-primary"><x-icon name="currency" class="size-4"/> Liquidar al productor</button>
                                    </form>
                                @endcan
                            @endif
                            @if ($lot->producer && auth()->user()->can('treasury.view'))
                                <a href="{{ route('accounts.show', ['producer', $lot->producer_id]) }}" class="btn btn-secondary"><x-icon name="book" class="size-4"/> Cuenta del productor</a>
                            @endif
                        </div>
                    @endif
                </x-panel>
            @endif

            <x-panel title="Pallets" :padding="false">
                <table class="table">
                    <thead><tr><th>Código</th><th>Ingreso</th><th>Variedad</th><th class="num">Cajones</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($pallets as $pallet)
                            <tr>
                                <td>@if (Route::has('pallets.show'))<a class="code link" href="{{ route('pallets.show', $pallet) }}">{{ $pallet->code }}</a>@else<span class="code">{{ $pallet->code }}</span>@endif</td>
                                <td class="tabular-nums">{{ fdate($pallet->received_at, true) }}</td>
                                <td>{{ $pallet->variety?->name ?? '—' }}</td>
                                <td class="num">{{ num($pallet->crates_count) }}</td>
                                <td><x-status :status="$pallet->status"/></td>
                            </tr>
                        @empty
                            <x-empty colspan="5" message="El lote todavía no tiene pallets."/>
                        @endforelse
                    </tbody>
                </table>
                @if ($pallets->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $pallets->links() }}</div>@endif
            </x-panel>

            <x-panel title="Cajones" :padding="false">
                <table class="table">
                    <thead><tr><th>Código</th><th>Variedad</th><th>Tamaño</th><th>Embalador</th><th class="num">Peso</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($crates as $crate)
                            <tr>
                                <td>@if (Route::has('crates.show'))<a class="code link" href="{{ route('crates.show', $crate) }}">{{ $crate->code }}</a>@else<span class="code">{{ $crate->code }}</span>@endif</td>
                                <td>{{ $crate->variety?->name ?? '—' }}</td>
                                <td>{{ $crate->size?->name ?? '—' }}</td>
                                <td>{{ $crate->packer?->full_name ?? '—' }}</td>
                                <td class="num">{{ $crate->weight ? num($crate->weight, 2) : '—' }}</td>
                                <td><x-status :status="$crate->status"/></td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="El lote todavía no tiene cajones."/>
                        @endforelse
                    </tbody>
                </table>
                @if ($crates->hasPages())<div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $crates->links() }}</div>@endif
            </x-panel>
        </div>

        <x-panel title="Historial de estados">
            <x-timeline :events="$lot->stateHistories->map(fn ($h) => [
                'time' => $h->created_at,
                'title' => \App\Models\Lot::STATUSES[$h->to_state] ?? $h->to_state,
                'detail' => $h->notes,
                'user' => $h->user?->full_name,
                'color' => $h->to_state === 'voided' ? 'red' : ($h->to_state === 'closed' ? 'sky' : 'brand'),
            ])->all()"/>
        </x-panel>
    </div>

    @can('lots.manage')
        <x-modal name="void-lot" title="Anular lote {{ $lot->code }}">
            <form method="POST" action="{{ route('lots.void', $lot) }}" class="space-y-4" x-data x-confirm="¿Confirmás la anulación del lote? Esta acción queda auditada.">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'void-lot')">Cancelar</button>
                    <button class="btn btn-danger">Anular lote</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
