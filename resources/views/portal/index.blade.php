@php
    $who = collect([$user->client?->business_name, $user->owner?->name])->filter()->unique()->join(' · ');
@endphp
<x-layouts.app title="Mi mercadería">
    <x-page-header title="Mi mercadería" :subtitle="$who ?: null"/>

    @if (! $linked)
        <div class="panel p-10 text-center">
            <x-icon name="briefcase" class="mx-auto mb-2 size-8 text-stone-300 dark:text-stone-600"/>
            <p class="text-sm text-stone-600 dark:text-stone-300">Tu usuario todavía no está vinculado a un cliente o propietario. Pedile al administrador que lo configure.</p>
        </div>
    @else
        <nav class="mb-6 flex flex-wrap gap-1 border-b border-stone-200 dark:border-stone-800" aria-label="Secciones">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('portal.index', ['tab' => $key]) }}" @class([
                    '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
                    'border-brand-600 text-brand-700 dark:text-brand-400' => $tab === $key,
                    'border-transparent text-stone-500 hover:text-stone-800 dark:hover:text-stone-200' => $tab !== $key,
                ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($tab === 'summary')
            <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @if ($user->owner_id)
                    <x-stat label="Cajones en galpón" :value="num($summary['crates_in_stock'])" icon="box"/>
                    <x-stat label="Kg en galpón" :value="kg($summary['kg_in_stock'], 0)" icon="scale" color="accent"/>
                    <x-stat label="Pallets de la temporada" :value="num($summary['pallets_season'])" icon="pallet" color="sky"/>
                @endif
                <x-stat label="Cargas despachadas" :value="num($summary['loads_dispatched'])" icon="truck" color="amber"/>
                @if ($user->client_id)
                    <x-stat label="Total facturado" :value="money($summary['invoiced'])" icon="receipt" color="violet"/>
                @endif
            </div>

            <x-panel title="Últimas cargas" :padding="false">
                <table class="table">
                    <thead><tr><th>Carga</th><th>Fecha</th><th>Destino</th><th class="num">Cajones</th><th class="num">Kg</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($recentLoads as $load)
                            <tr>
                                <td class="code">{{ $load->number }}</td>
                                <td>{{ fdate($load->date) }}</td>
                                <td>{{ $load->destination?->name ?? '—' }}</td>
                                <td class="num">{{ num($load->total_crates) }}</td>
                                <td class="num">{{ kg($load->total_kg) }}</td>
                                <td><x-status :status="$load->status"/></td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="Todavía no hay cargas."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        @elseif ($tab === 'stock')
            <x-table>
                <thead><tr><th>Pallet</th><th>Ingreso</th><th>Lote</th><th>Variedad</th><th class="num">Cajones</th><th class="num">Kg brutos</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($rows as $pallet)
                        <tr>
                            <td class="code">{{ $pallet->code }}</td>
                            <td>{{ fdate($pallet->received_at, true) }}</td>
                            <td class="code">{{ $pallet->lot?->code ?? '—' }}</td>
                            <td>{{ $pallet->variety?->name ?? '—' }}</td>
                            <td class="num">{{ num($pallet->crates_count) }}</td>
                            <td class="num">{{ kg($pallet->gross_weight) }}</td>
                            <td><x-status :status="$pallet->status"/></td>
                        </tr>
                    @empty
                        <x-empty colspan="7"/>
                    @endforelse
                </tbody>
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            </x-table>
        @elseif ($tab === 'loads')
            <x-table>
                <thead><tr><th>Carga</th><th>Fecha</th><th>Destino</th><th>Camión</th><th class="num">Cajones</th><th class="num">Kg</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($rows as $load)
                        <tr>
                            <td class="code">{{ $load->number }}</td>
                            <td>{{ fdate($load->date) }}</td>
                            <td>{{ $load->destination?->name ?? '—' }}</td>
                            <td class="code">{{ $load->truck?->plate ?? '—' }}</td>
                            <td class="num">{{ num($load->total_crates) }}</td>
                            <td class="num">{{ kg($load->total_kg) }}</td>
                            <td><x-status :status="$load->status"/></td>
                        </tr>
                    @empty
                        <x-empty colspan="7"/>
                    @endforelse
                </tbody>
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            </x-table>
        @elseif ($tab === 'remitos')
            <x-table>
                <thead><tr><th>Remito</th><th>Emisión</th><th>Destino</th><th class="num">Cajones</th><th class="num">Kg</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $remito)
                        <tr>
                            <td class="code">{{ $remito->number }}</td>
                            <td>{{ fdate($remito->issued_at, true) }}</td>
                            <td>{{ $remito->destination?->name ?? '—' }}</td>
                            <td class="num">{{ num($remito->total_crates) }}</td>
                            <td class="num">{{ kg($remito->total_kg) }}</td>
                            <td><x-status :status="$remito->status"/></td>
                            <td class="text-right">
                                @if ($remito->public_token)
                                    <a href="{{ route('remitos.public', $remito->public_token) }}" class="link" target="_blank" rel="noopener">Ver</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7"/>
                    @endforelse
                </tbody>
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            </x-table>
        @elseif ($tab === 'invoices')
            <x-table>
                <thead><tr><th>Comprobante</th><th>Fecha</th><th>CAE</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $invoice)
                        <tr>
                            <td class="code">{{ $invoice->formattedNumber() }}</td>
                            <td>{{ fdate($invoice->issued_on) }}</td>
                            <td class="code">{{ $invoice->cae }}</td>
                            <td class="num">{{ money($invoice->total_amount) }}</td>
                            <td class="text-right"><a href="{{ route('portal.invoices.pdf', $invoice) }}" class="link">Descargar PDF</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="5"/>
                    @endforelse
                </tbody>
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            </x-table>
        @endif
    @endif
</x-layouts.app>
