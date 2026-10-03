<x-layouts.app :title="'DTV-e '.$document->number">
    <x-page-header :title="'DTV-e '.$document->number" :subtitle="$document->directionLabel().' · '.fdate($document->date)" :back="route('dtv.index')">
        <x-slot:actions>
            @can('dtv.manage')
                <a href="{{ route('dtv.edit', $document) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Corregir</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Líneas" class="lg:col-span-2" :padding="false">
            <x-table class="border-0 shadow-none">
                <thead><tr><th>Especie</th><th>Variedad</th><th class="num">Cantidad</th><th>Unidad</th><th class="num">Kg por unidad</th><th class="num">Kg totales</th></tr></thead>
                <tbody>
                    @foreach ($document->lines as $line)
                        <tr>
                            <td>{{ $line->species ?: '—' }}</td>
                            <td>{{ $line->varietyLabel() ?: '—' }}</td>
                            <td class="num">{{ num($line->quantity, 2) }}</td>
                            <td>{{ $line->unit }}</td>
                            <td class="num">{{ $line->kg_per_unit !== null ? num($line->kg_per_unit, 2) : '—' }}</td>
                            <td class="num font-medium">{{ num($line->kg_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <x-slot:footer>
                    <div class="flex justify-between text-sm font-semibold"><span>Total</span><span class="tabular-nums">{{ kg($document->lines->sum('kg_total')) }}</span></div>
                </x-slot:footer>
            </x-table>
        </x-panel>

        <div class="space-y-6">
            <x-panel title="Datos">
                <x-dl :items="[
                    'Ingreso / egreso' => $document->directionLabel(),
                    'Tipo' => $document->doc_type,
                    'Emisor' => $document->issuer,
                    'Establecimiento' => $document->establishment,
                    'Destinatario' => $document->recipient,
                    'Destino' => $document->destination,
                    'Transporte' => $document->transport,
                    'Carga' => $document->relatedLoad?->number,
                    'Lote' => $document->lot?->code,
                    'Observaciones' => $document->notes,
                ]"/>
            </x-panel>
            @can('dtv.manage')
                <x-panel title="Eliminar">
                    <p class="text-sm text-stone-600 dark:text-stone-400">Si se cargó por error. Queda registrado quién lo eliminó y por qué.</p>
                    <div class="mt-3"><x-void-button :action="route('dtv.destroy', $document)" label="Eliminar este DTV-e" title="¿Eliminar el DTV-e {{ $document->number }}?"/></div>
                </x-panel>
            @endcan
        </div>
    </div>
</x-layouts.app>
