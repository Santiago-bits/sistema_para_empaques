<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->voucherLabel() }} {{ $invoice->formattedNumber() }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111; }
        .header { width: 100%; border: 1px solid #111; margin-bottom: 10px; }
        .header td { vertical-align: top; padding: 8px; }
        .letter { font-size: 28pt; font-weight: bold; border: 1px solid #111; width: 46px; text-align: center; }
        .title { font-size: 15pt; font-weight: bold; }
        .muted { color: #555; font-size: 8pt; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #eee; border: 1px solid #999; padding: 5px; text-align: left; }
        table.data td { border: 1px solid #999; padding: 5px; }
        .num { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { border-top: 2px solid #111; font-weight: bold; font-size: 11pt; }
        .watermark { position: fixed; top: 38%; left: 8%; font-size: 54pt; color: rgba(200, 0, 0, .15); transform: rotate(-25deg); }
    </style>
</head>
<body>
    @if ($invoice->status->value !== 'authorized')
        <div class="watermark">{{ $invoice->status->value === 'voided' ? 'ANULADO' : 'BORRADOR — SIN VALIDEZ' }}</div>
    @elseif ($invoice->arca_mode !== 'production')
        <div class="watermark">SIMULADO — SIN VALIDEZ FISCAL</div>
    @endif

    @php
        $letter = ['1' => 'A', '3' => 'A', '6' => 'B', '8' => 'B', '11' => 'C', '13' => 'C'][(string) $invoice->voucher_type] ?? '';
    @endphp
    <table class="header">
        <tr>
            <td style="width:45%">
                <div class="title">{{ setting('company.name') }}</div>
                <div>{{ setting('company.address') }}</div>
                <div class="muted">{{ setting('arca.emitter_condition', 'RI') === 'MT' ? 'Responsable Monotributo' : 'IVA Responsable Inscripto' }}</div>
            </td>
            <td style="width:10%; text-align:center"><div class="letter">{{ $letter }}</div><div class="muted">Cód. {{ str_pad((string) $invoice->voucher_type, 3, '0', STR_PAD_LEFT) }}</div></td>
            <td style="width:45%">
                <div class="title">{{ mb_strtoupper($invoice->voucherLabel()) }}</div>
                <div>N° <strong>{{ $invoice->formattedNumber() }}</strong></div>
                <div>Fecha: {{ fdate($invoice->issued_on) }}</div>
                <div>CUIT: {{ \App\Rules\Cuit::format(setting('arca.cuit') ?: setting('company.cuit')) }}</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; border:1px solid #111; margin-bottom:10px">
        <tr><td style="padding:8px">
            <strong>Cliente:</strong> {{ $invoice->client->business_name }}<br>
            <strong>CUIT/DNI:</strong> {{ \App\Rules\Cuit::format($invoice->client->cuit) ?? $invoice->client->dni ?? '—' }} ·
            <strong>Condición IVA:</strong> {{ \App\Models\Client::TAX_CONDITIONS[$invoice->client->tax_condition] ?? '' }}<br>
            <strong>Domicilio:</strong> {{ trim($invoice->client->address.' '.$invoice->client->locality.' '.$invoice->client->province) ?: '—' }}
            @if ($invoice->associated)
                <br><strong>Comprobante asociado:</strong> {{ $invoice->associated->voucherLabel() }} N° {{ $invoice->associated->formattedNumber() }} del {{ fdate($invoice->associated->issued_on) }}
            @endif
        </td></tr>
    </table>

    <table class="data">
        <thead><tr><th>Descripción</th><th class="num">Cantidad</th><th class="num">Precio unit.</th>@if ($letter === 'A')<th class="num">IVA</th>@endif<th class="num">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ num($item->quantity, 2) }} {{ $item->unit }}</td>
                    <td class="num">{{ num($item->unit_price, 2) }}</td>
                    @if ($letter === 'A')<td class="num">{{ num($item->vat_rate, 1) }} %</td>@endif
                    <td class="num">{{ num($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($letter === 'A')
            <tr><td>Neto gravado</td><td class="num">{{ money($invoice->net_amount, $invoice->currency) }}</td></tr>
            <tr><td>IVA</td><td class="num">{{ money($invoice->vat_amount, $invoice->currency) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="num">{{ money($invoice->total_amount, $invoice->currency) }}</td></tr>
    </table>

    @if ($invoice->status->value === 'authorized')
        <table style="width:100%; margin-top:30px">
            <tr>
                <td style="width:130px">@if ($qr)<img src="{{ $qr }}" style="width:120px; height:120px">@endif</td>
                <td>
                    <strong>CAE N°:</strong> {{ $invoice->cae }}<br>
                    <strong>Vencimiento CAE:</strong> {{ fdate($invoice->cae_expires_on) }}<br>
                    <span class="muted">Comprobante autorizado por ARCA.</span>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
