@php $status = $invoice->status->value; @endphp
<x-layouts.app :title="$invoice->voucherLabel().' '.$invoice->formattedNumber()">
    <x-page-header :title="$invoice->voucherLabel().' '.$invoice->formattedNumber()" :subtitle="$invoice->client?->business_name.' · '.fdate($invoice->issued_on)" :back="route('invoices.index')">
        <x-slot:actions>
            <x-status :status="$invoice->status" class="text-sm"/>
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-secondary"><x-icon name="download" class="size-4"/> PDF</a>
            @if (in_array($status, ['draft', 'rejected'], true))
                @can('billing.void')
                    <button type="button" class="btn btn-ghost text-red-600" @click="$dispatch('open-modal', 'void-invoice')">Anular</button>
                @endcan
                @can('billing.manage')
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-secondary"><x-icon name="pencil" class="size-4"/> Editar</a>
                @endcan
                @can('arca.manage')
                    <form method="POST" action="{{ route('invoices.submit', $invoice) }}" x-data
                          x-confirm="{{ $mode === 'production' ? '¿Enviar a ARCA en PRODUCCIÓN? Se emitirá un comprobante fiscal real.' : '¿Enviar a ARCA ('.(\App\Enums\ArcaMode::tryFrom($mode)?->label()).')?' }}">
                        @csrf
                        <button class="btn btn-primary"><x-icon name="bank" class="size-4"/> {{ $status === 'rejected' ? 'Reintentar envío' : 'Enviar a ARCA' }}</button>
                    </form>
                @endcan
            @endif
            @if ($status === 'pending')
                @can('arca.manage')
                    <form method="POST" action="{{ route('invoices.reconcile', $invoice) }}">
                        @csrf
                        <button class="btn btn-primary"><x-icon name="refresh" class="size-4"/> Verificar en ARCA</button>
                    </form>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($status === 'pending')
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            <strong>Pendiente de respuesta de ARCA.</strong> {{ $invoice->last_error ?: 'El envío está en curso o no se recibió respuesta.' }}
            No lo reenvíes: usá «Verificar en ARCA» para saber si quedó autorizado.
        </div>
    @endif
    @if ($invoice->associated)
        <p class="mb-4 text-sm text-stone-600 dark:text-stone-300">Ajusta a: <a href="{{ route('invoices.show', $invoice->associated) }}" class="link code">{{ $invoice->associated->voucherLabel() }} {{ $invoice->associated->formattedNumber() }}</a></p>
    @endif

    @if ($status === 'rejected' && $invoice->last_error)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
            <strong>Rechazado por ARCA:</strong> {{ $invoice->last_error }}
        </div>
    @endif
    @if ($invoice->arca_mode === 'simulation' && $status === 'authorized')
        <div class="mb-6 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
            Comprobante <strong>SIMULADO</strong>: el CAE es ficticio y no tiene validez fiscal.
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Neto" :value="money($invoice->net_amount, $invoice->currency)" icon="currency"/>
        <x-stat label="IVA" :value="money($invoice->vat_amount, $invoice->currency)" icon="receipt" color="sky"/>
        <x-stat label="Total" :value="money($invoice->total_amount, $invoice->currency)" icon="bank" color="accent"/>
        <x-stat label="CAE" :value="$invoice->cae ?? '—'" icon="check-badge" color="violet" :hint="$invoice->cae_expires_on ? 'Vence '.fdate($invoice->cae_expires_on) : null"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Ítems" :padding="false">
                <table class="table">
                    <thead><tr><th>Descripción</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">IVA</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="num">{{ num($item->quantity, 2) }} {{ $item->unit }}</td>
                                <td class="num">{{ money($item->unit_price, $invoice->currency) }}</td>
                                <td class="num">{{ num($item->vat_rate, 1) }} %</td>
                                <td class="num">{{ money($item->subtotal, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>

            <x-panel title="Envíos a ARCA" :padding="false">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Modo</th><th>Intento</th><th>Resultado</th><th>Detalle</th><th>Usuario</th></tr></thead>
                    <tbody>
                        @forelse ($invoice->arcaRecords as $record)
                            <tr>
                                <td class="tabular-nums">{{ fdate($record->created_at, true) }}</td>
                                <td>{{ \App\Enums\ArcaMode::tryFrom($record->mode)?->label() }}</td>
                                <td class="num">{{ $record->attempt }}</td>
                                <td><x-badge :color="$record->status === 'success' ? 'emerald' : 'red'">{{ $record->status === 'success' ? 'Aprobado' : 'Error' }}</x-badge></td>
                                <td class="max-w-xs truncate text-xs text-stone-500" title="{{ $record->error_message }}">{{ $record->error_message }}</td>
                                <td>{{ $record->user?->full_name }}</td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="Todavía no se envió a ARCA."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
            @include('documents._panel', ['documentable' => $invoice])
        </div>

        <div class="space-y-6">
            <x-panel title="Datos">
                <x-dl class="!grid-cols-1" :items="[
                    'Cliente' => $invoice->client?->business_name,
                    'CUIT' => \App\Rules\Cuit::format($invoice->client?->cuit),
                    'Condición IVA' => \App\Models\Client::TAX_CONDITIONS[$invoice->client?->tax_condition] ?? null,
                    'Punto de venta' => str_pad((string) $invoice->point_of_sale, 4, '0', STR_PAD_LEFT),
                    'Moneda' => $invoice->currency.($invoice->currency === 'USD' ? ' · cotización '.num($invoice->exchange_rate, 4) : ''),
                    'Carga' => $invoice->loadRecord?->number,
                    'Remito' => $invoice->remito?->number,
                    'Creado por' => $invoice->creator?->full_name,
                    'Observaciones' => $invoice->notes,
                ]"/>
            </x-panel>
            <x-panel title="Historial">
                <x-timeline :events="$invoice->stateHistories->map(fn ($h) => [
                    'time' => $h->created_at,
                    'title' => \App\Enums\InvoiceStatus::tryFrom($h->to_state)?->label() ?? $h->to_state,
                    'detail' => $h->notes,
                    'user' => $h->user?->full_name,
                    'color' => in_array($h->to_state, ['rejected', 'voided'], true) ? 'red' : 'brand',
                ])->all()"/>
            </x-panel>
        </div>
    </div>

    @can('billing.void')
        <x-modal name="void-invoice" title="Anular comprobante">
            <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="space-y-4" x-data x-confirm="¿Anular el comprobante?">
                @csrf
                <x-textarea name="reason" label="Motivo" required rows="2"/>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'void-invoice')">Cancelar</button>
                    <button class="btn btn-danger">Anular</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
