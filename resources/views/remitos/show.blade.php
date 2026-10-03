<x-layouts.app :title="'Remito '.$remito->number">
    <x-page-header :title="'Remito '.$remito->number" :subtitle="'Emitido '.fdate($remito->issued_at, true).' · Carga '.$remito->loadRecord?->number" :back="route('remitos.index')">
        <x-slot:actions>
            <x-status :status="$remito->status" class="text-sm"/>
            <a href="{{ route('remitos.pdf', $remito) }}" class="btn btn-secondary"><x-icon name="download" class="size-4"/> PDF</a>
            @if ($remito->status->value === 'issued')
                @can('remitos.void')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'void-remito')">Anular</button>
                @endcan
                @if (in_array($remito->loadRecord?->status->value, ['dispatched'], true))
                    @can('remitos.deliver')
                        <a href="{{ route('remitos.deliver.show', $remito) }}" class="btn btn-primary"><x-icon name="check" class="size-4"/> Registrar entrega</a>
                    @endcan
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos">
                <x-dl :items="[
                    'Cliente' => $remito->client?->business_name,
                    'CUIT cliente' => \App\Rules\Cuit::format($remito->client?->cuit),
                    'Destino' => $remito->destination?->name,
                    'Camión' => $remito->truck?->plate,
                    'Camionero' => $remito->driver?->full_name,
                    'Emitido por' => $remito->creator?->full_name,
                    'Observaciones' => $remito->notes,
                ]"/>
            </x-panel>
            <x-panel title="Detalle" :padding="false">
                <table class="table">
                    <thead><tr><th>Variedad</th><th>Tamaño</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @foreach ($remito->items as $item)
                            <tr><td>{{ $item->variety?->name ?? 'Sin variedad' }}</td><td>{{ $item->size?->name ?? 'Sin tamaño' }}</td><td class="num">{{ num($item->crates) }}</td><td class="num">{{ num($item->kg, 1) }}</td></tr>
                        @endforeach
                        <tr class="font-semibold"><td colspan="2">Total</td><td class="num">{{ num($remito->total_crates) }}</td><td class="num">{{ num($remito->total_kg, 1) }}</td></tr>
                    </tbody>
                </table>
            </x-panel>
            @if ($remito->status->value === 'delivered')
                <x-panel title="Entrega">
                    <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                        <x-dl :items="[
                            'Fecha y hora' => fdate($remito->delivered_at, true),
                            'Receptor' => $remito->receiver_name,
                            'DNI' => $remito->receiver_dni,
                            'Observaciones' => $remito->delivery_notes,
                        ]"/>
                        @if ($remito->signature_path)
                            <img src="{{ route('remitos.signature', $remito) }}" alt="Firma del receptor" class="h-28 rounded border border-stone-200 bg-white p-1 dark:border-stone-700">
                        @endif
                    </div>
                </x-panel>
            @endif
            @include('documents._panel', ['documentable' => $remito])
        </div>

        <div class="space-y-6">
            <x-panel title="QR de consulta">
                <img src="{{ $qr }}" alt="QR del remito" class="mx-auto size-40 rounded bg-white p-2">
                <p class="mt-2 text-center text-xs break-all text-stone-500">{{ $publicUrl }}</p>
                <p class="mt-1 text-center text-xs text-stone-500">Muestra sólo número, carga, estado, fecha y destino.</p>
            </x-panel>
            <x-panel title="Historial">
                <x-timeline :events="$remito->stateHistories->map(fn ($h) => [
                    'time' => $h->created_at,
                    'title' => \App\Enums\RemitoStatus::tryFrom($h->to_state)?->label() ?? $h->to_state,
                    'detail' => $h->notes,
                    'user' => $h->user?->full_name,
                    'color' => $h->to_state === 'voided' ? 'red' : 'brand',
                ])->all()"/>
            </x-panel>
        </div>
    </div>

    @can('remitos.void')
        <x-modal name="void-remito" title="Anular remito {{ $remito->number }}">
            <form method="POST" action="{{ route('remitos.void', $remito) }}" class="space-y-4" x-data x-confirm="¿Anular el remito? La carga podrá emitir uno nuevo.">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'void-remito')">Cancelar</button>
                    <button class="btn btn-danger">Anular remito</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
