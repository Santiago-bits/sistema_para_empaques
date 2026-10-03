@php $status = $load->status; @endphp
<x-layouts.app :title="'Carga '.$load->number">
    <x-page-header :title="'Carga '.$load->number" :subtitle="fdate($load->date).' · '.($load->client?->business_name ?? 'Sin cliente').' · '.($load->destination?->name ?? 'Sin destino')" :back="route('loads.index')">
        <x-slot:actions>
            <x-status :status="$status" class="text-sm"/>
            @if (in_array($status->value, ['closed', 'dispatched', 'delivered'], true))
                @can('dtv.manage')
                    <a href="{{ route('dtv.create', ['load' => $load->id]) }}" class="btn btn-secondary"><x-icon name="document" class="size-4"/> DTV-e de egreso</a>
                @endcan
                @can('loads.update')
                    <a href="{{ route('loads.correct', $load) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Corregir datos</a>
                @endcan
            @endif
            @if ($status->value === 'draft')
                @can('loads.cancel')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'cancel-load')">Cancelar carga</button>
                @endcan
                @can('loads.update')
                    <a href="{{ route('loads.edit', $load) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Datos</a>
                    <a href="{{ route('loads.builder', $load) }}" class="btn btn-primary"><x-icon name="box" class="size-4"/> Armar carga</a>
                @endcan
            @elseif ($status->value === 'closed')
                @can('loads.reopen')
                    <button type="button" class="btn btn-ghost" @click="$dispatch('open-modal', 'reopen-load')">Reabrir</button>
                @endcan
                @if (! $remito || $remito->status->value === 'voided')
                    @can('remitos.create')
                        <form method="POST" action="{{ route('remitos.store', $load) }}" x-data x-confirm="¿Emitir el remito de la carga {{ $load->number }}?">
                            @csrf
                            <button class="btn btn-secondary"><x-icon name="document" class="size-4"/> Emitir remito</button>
                        </form>
                    @endcan
                @endif
                @can('loads.dispatch')
                    <a href="{{ route('loads.dispatch.show', $load) }}" class="btn btn-primary"><x-icon name="truck" class="size-4"/> Checklist y despacho</a>
                @endcan
            @endif
            @if (in_array($status->value, ['closed', 'dispatched', 'delivered'], true))
                @can('billing.manage')
                    @if (Route::has('invoices.create'))
                        <a href="{{ route('invoices.create', ['load_id' => $load->id]) }}" class="btn btn-secondary"><x-icon name="receipt" class="size-4"/> Facturar</a>
                    @endif
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Cajones" :value="num($summary['crates'])" icon="box" :hint="$load->planned_crates ? 'de '.num($load->planned_crates).' previstos' : null"/>
        <x-stat label="Kg" :value="kg($summary['kg'], 1)" icon="scale" color="accent"/>
        <x-stat label="Pallets" :value="num($summary['pallets'])" icon="pallet" color="sky"/>
        <x-stat label="Promedio por cajón" :value="kg($summary['avg_kg'])" icon="chart-bar" color="violet"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Transporte y destino">
                <x-dl :items="[
                    'Cliente' => $load->client?->business_name,
                    'Destino' => $load->destination?->name,
                    'Propietario' => $load->owner?->name,
                    'Transportista' => $load->transporter?->business_name ?? $load->truck?->transporter?->business_name,
                    'Camión' => $load->truck ? $load->truck->plate.' — '.trim($load->truck->brand.' '.$load->truck->model) : null,
                    'Camionero' => $load->driver ? $load->driver->full_name.' (DNI '.$load->driver->dni.')' : null,
                    'Acoplado' => $load->trailer_plate,
                    'N° de guía' => $load->guide_number,
                    'Destino comercial' => \App\Models\Load::COMMERCIAL_DESTINATIONS[$load->commercial_destination] ?? null,
                    'Canal' => \App\Models\Load::SALES_CHANNELS[$load->sales_channel] ?? null,
                    'Condición de venta' => \App\Models\Load::SALE_CONDITIONS[$load->sale_condition] ?? null,
                    'Precio por unidad' => $load->unit_price !== null ? money($load->unit_price) : null,
                    'Subtotal' => $load->subtotal() !== null ? money($load->subtotal()).' ('.num($load->total_crates).' × '.money($load->unit_price).')' : null,
                    'Flete' => $load->freight_amount !== null ? money($load->freight_amount).($load->freight_posted_at ? ' · imputado al transportista' : '') : null,
                    'Creada por' => $load->creator?->full_name,
                    'Cerrada' => $load->closed_at ? fdate($load->closed_at, true).' · '.$load->closer?->full_name : null,
                    'Despachada' => $load->dispatched_at ? fdate($load->dispatched_at, true).' · '.$load->dispatcher?->full_name : null,
                    'Observaciones' => $load->notes,
                ]"/>
            </x-panel>

            <x-panel title="Detalle por variedad y tamaño" :padding="false">
                <table class="table">
                    <thead><tr><th>Variedad</th><th>Tamaño</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                    <tbody>
                        @forelse ($summary['by_variety_size'] as $row)
                            <tr><td>{{ $row['variety'] }}</td><td>{{ $row['size'] }}</td><td class="num">{{ num($row['crates']) }}</td><td class="num">{{ num($row['kg'], 1) }}</td></tr>
                        @empty
                            <x-empty colspan="4" message="La carga no tiene cajones."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>

            @if ($remito || $invoices->isNotEmpty())
                <x-panel title="Documentación comercial" :padding="false">
                    <table class="table">
                        <tbody>
                            @if ($remito)
                                <tr>
                                    <td class="w-32">Remito</td>
                                    <td><a href="{{ route('remitos.show', $remito) }}" class="code link">{{ $remito->number }}</a></td>
                                    <td><x-status :status="$remito->status"/></td>
                                    <td class="text-right"><a href="{{ route('remitos.pdf', $remito) }}" class="link">PDF</a></td>
                                </tr>
                            @endif
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td>{{ $invoice->voucherLabel() }}</td>
                                    <td>@if (Route::has('invoices.show'))<a href="{{ route('invoices.show', $invoice) }}" class="code link">{{ $invoice->formattedNumber() }}</a>@else<span class="code">{{ $invoice->formattedNumber() }}</span>@endif</td>
                                    <td><x-status :status="$invoice->status"/></td>
                                    <td class="num">@can('billing.view'){{ money($invoice->total_amount, $invoice->currency) }}@endcan</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-panel>
            @endif

            @include('documents._panel', ['documentable' => $load])
        </div>

        <div class="space-y-6">
            @if ($load->dispatchChecks->isNotEmpty())
                <x-panel title="Checklist de despacho" :padding="false">
                    <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                        @foreach (\App\Models\DispatchCheck::ITEMS as $key => $label)
                            @php $check = $load->dispatchChecks->firstWhere('item', $key); @endphp
                            <li class="flex items-center justify-between px-4 py-2">
                                <span>{{ $label }}</span>
                                @if ($check?->checked)
                                    <span class="text-xs text-emerald-600" title="{{ fdate($check->checked_at, true) }}">✔ {{ $check->user?->full_name }}</span>
                                @else
                                    <span class="text-xs text-stone-400">Pendiente</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif
            <x-panel title="Historial">
                <x-timeline :events="$load->stateHistories->map(fn ($h) => [
                    'time' => $h->created_at,
                    'title' => \App\Enums\LoadStatus::tryFrom($h->to_state)?->label() ?? $h->to_state,
                    'detail' => $h->notes,
                    'user' => $h->user?->full_name,
                    'color' => $h->to_state === 'cancelled' ? 'red' : ($h->to_state === 'draft' ? 'amber' : 'brand'),
                ])->all()"/>
            </x-panel>
        </div>
    </div>

    @can('loads.reopen')
        <x-modal name="reopen-load" title="Reabrir carga {{ $load->number }}">
            <form method="POST" action="{{ route('loads.reopen', $load) }}" class="space-y-4" x-data x-confirm="¿Reabrir la carga? Los cajones vuelven a quedar reservados y el checklist se reinicia.">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'reopen-load')">Cancelar</button>
                    <button class="btn btn-warning">Reabrir</button>
                </div>
            </form>
        </x-modal>
    @endcan
    @can('loads.cancel')
        <x-modal name="cancel-load" title="Cancelar carga {{ $load->number }}">
            <form method="POST" action="{{ route('loads.cancel', $load) }}" class="space-y-4" x-data x-confirm="¿Cancelar la carga? Todos sus cajones quedan disponibles nuevamente.">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'cancel-load')">Volver</button>
                    <button class="btn btn-danger">Cancelar carga</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
