@php $editing = $invoice->exists; @endphp
<x-layouts.app :title="$editing ? 'Editar comprobante' : 'Nuevo comprobante'">
    <x-page-header :title="$editing ? 'Editar '.$invoice->voucherLabel().' '.$invoice->formattedNumber() : 'Nuevo comprobante'"
                   subtitle="Los importes se recalculan en el servidor al guardar."
                   :back="$editing ? route('invoices.show', $invoice) : route('invoices.index')"/>

    <form method="POST" action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6"
          x-data="invoiceForm({{ \Illuminate\Support\Js::from(['items' => array_values(old('items', $items))]) }})">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="load_id" value="{{ old('load_id', $invoice->load_id) }}">

        <x-panel title="Encabezado">
            <div class="grid gap-4 md:grid-cols-3">
                <x-field label="Cliente" name="client_id" :required="true">
                    <select name="client_id" id="client_id" class="form-input" required>
                        <option value="">Seleccionar…</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id', $invoice->client_id) === (string) $client->id)>
                                {{ $client->business_name }} ({{ \App\Models\Client::TAX_CONDITIONS[$client->tax_condition] ?? $client->tax_condition }})
                            </option>
                        @endforeach
                    </select>
                </x-field>
                <div x-data="{ type: '{{ (int) old('voucher_type', $invoice->voucher_type) }}' }" class="space-y-4">
                    <x-select name="voucher_type" label="Tipo de comprobante" :options="$types" :value="$invoice->voucher_type" required x-model="type"
                              hint="A: cliente Resp. Inscripto · B: resto · C: si el emisor es monotributista."/>
                    <div x-show="['3', '8', '13'].includes(String(type))" x-cloak>
                        <x-select name="associated_invoice_id" label="Factura que ajusta" :options="$associable" :value="$invoice->associated_invoice_id" placeholder="Elegí la factura…"
                                  hint="Obligatorio para notas de crédito: misma letra y mismo cliente."/>
                    </div>
                </div>
                <x-input name="issued_on" type="date" label="Fecha de emisión" :value="$invoice->issued_on" required/>
                @php $usdNow = \App\Models\ExchangeRate::current(); @endphp
                {{-- Al pasar a dólares se propone la cotización vigente (si todavía no se cargó una). --}}
                <x-select name="currency" label="Moneda" :options="['ARS' => 'Pesos (ARS)', 'USD' => 'Dólares (USD)']" :value="$invoice->currency" x-model="currency"
                          x-on:change="if ($event.target.value === 'USD' && {{ \Illuminate\Support\Js::from($usdNow ? num($usdNow->sell, 2) : null) }}) { const f = document.querySelector('[name=exchange_rate]'); if (f && (! f.value || parseFloat(f.value.replace(/\./g, '').replace(',', '.')) <= 1)) f.value = {{ \Illuminate\Support\Js::from($usdNow ? num($usdNow->sell, 2) : null) }}; }"/>
                <div x-show="currency === 'USD'"><x-input name="exchange_rate" label="Cotización" :value="$invoice->exchange_rate" inputmode="decimal"
                     :hint="$usdNow ? 'Vigente: '.money($usdNow->sell).' del '.fdate($usdNow->date).'.' : null"/></div>
            </div>
        </x-panel>

        <x-panel title="Ítems" :padding="false">
            <table class="table">
                <thead><tr><th>Descripción</th><th class="w-32">Cantidad</th><th class="w-20">Unidad</th><th class="w-36">Precio unit.</th><th class="w-32">IVA</th><th class="num w-32">Subtotal</th><th class="w-8"></th></tr></thead>
                <tbody>
                    <template x-for="(item, i) in items" :key="i">
                        <tr>
                            <td><input class="form-input" :name="`items[${i}][description]`" x-model="item.description" required maxlength="255"></td>
                            <td><input class="form-input text-right" :name="`items[${i}][quantity]`" x-model="item.quantity" inputmode="decimal" required></td>
                            <td><input class="form-input" :name="`items[${i}][unit]`" x-model="item.unit" maxlength="10"></td>
                            <td><input class="form-input text-right" :name="`items[${i}][unit_price]`" x-model="item.unit_price" inputmode="decimal" required></td>
                            <td>
                                <select class="form-input" :name="`items[${i}][vat_rate]`" x-model="item.vat_rate">
                                    @foreach ($vatRates as $rate => $label)
                                        <option value="{{ $rate }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="num" x-text="fmt(subtotal(item))"></td>
                            <td><button type="button" class="text-red-600" @click="items.splice(i, 1)" aria-label="Quitar ítem"><x-icon name="x" class="size-4"/></button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 p-4 dark:border-stone-800">
                <button type="button" class="btn btn-secondary btn-sm" @click="items.push({ description: '', quantity: '', unit: 'kg', unit_price: '', vat_rate: '21' })"><x-icon name="plus" class="size-4"/> Agregar ítem</button>
                <div class="text-right text-sm">
                    <p>Neto: <strong class="tabular-nums" x-text="fmt(net())"></strong></p>
                    <p>IVA: <strong class="tabular-nums" x-text="fmt(vat())"></strong></p>
                    <p class="text-lg">Total: <strong class="tabular-nums" x-text="fmt(net() + vat())"></strong></p>
                    <p class="text-xs text-stone-500">Vista previa: el total definitivo lo calcula el servidor.</p>
                </div>
            </div>
            @error('items')<p class="form-error px-4 pb-3">{{ $message }}</p>@enderror
        </x-panel>

        <x-panel title="Observaciones"><x-textarea name="notes" :value="$invoice->notes"/></x-panel>

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar borrador</button>
        </div>
    </form>

    @push('scripts')
        <script>
            window.invoiceForm = function (config) {
                const n = v => Number(String(v ?? '').includes(',') ? String(v).replace(/\./g, '').replace(',', '.') : v) || 0;
                return {
                    items: config.items.map(i => Object.assign({ unit: 'kg', vat_rate: '21' }, i, { vat_rate: String(Number(i.vat_rate ?? 21)) })),
                    currency: document.querySelector('[name=currency]')?.value || 'ARS',
                    subtotal(i) { return Math.round(n(i.quantity) * n(i.unit_price) * 100) / 100; },
                    net() { return this.items.reduce((s, i) => s + this.subtotal(i), 0); },
                    vat() { return this.items.reduce((s, i) => s + Math.round(this.subtotal(i) * n(i.vat_rate)) / 100, 0); },
                    fmt(v) { return v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                };
            };
        </script>
    @endpush
</x-layouts.app>
