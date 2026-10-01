<x-layouts.app title="Facturación">
    <x-page-header title="Facturación" subtitle="Comprobantes electrónicos con CAE de ARCA.">
        <x-slot:actions>
            <x-badge :color="$mode === 'production' ? 'emerald' : ($mode === 'homologation' ? 'amber' : 'sky')" class="text-sm">
                ARCA: {{ \App\Enums\ArcaMode::tryFrom($mode)?->label() }}
            </x-badge>
            @can('billing.manage')
                <a href="{{ route('invoices.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nuevo comprobante</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-input name="number" label="Número" :value="request('number')" class="code" placeholder="0001-00000123"/>
        <x-select name="status" label="Estado" :options="$statuses" :value="request('status')" placeholder="Todos"/>
        <x-select name="voucher_type" label="Tipo" :options="$types" :value="request('voucher_type')" placeholder="Todos"/>
        <x-select name="client_id" label="Cliente" :options="$clients" :value="request('client_id')" placeholder="Todos"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table>
        <thead><tr><th>Comprobante</th><th>Fecha</th><th>Cliente</th><th>Carga</th><th class="num">Total</th><th>CAE</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('invoices.show', $invoice) }}" class="link">{{ $invoice->voucherLabel() }} <span class="code">{{ $invoice->formattedNumber() }}</span></a></td>
                    <td class="tabular-nums">{{ fdate($invoice->issued_on) }}</td>
                    <td>{{ $invoice->client?->business_name }}</td>
                    <td class="code">{{ $invoice->loadRecord?->number ?? '—' }}</td>
                    <td class="num">{{ money($invoice->total_amount, $invoice->currency) }}</td>
                    <td class="code text-xs">{{ $invoice->cae ?? '—' }}</td>
                    <td><x-status :status="$invoice->status"/></td>
                </tr>
            @empty
                <x-empty colspan="7"/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $invoices->links() }}</x-slot:footer>
    </x-table>
</x-layouts.app>
