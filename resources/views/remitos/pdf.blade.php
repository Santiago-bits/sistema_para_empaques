<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Remito {{ $remito->number }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        .header { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .header td { vertical-align: top; }
        .title { font-size: 20pt; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #555; font-size: 8.5pt; }
        .box { border: 1px solid #999; padding: 8px; margin-bottom: 10px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #eee; border: 1px solid #999; padding: 5px; font-size: 9pt; text-align: left; }
        table.data td { border: 1px solid #999; padding: 5px; }
        .num { text-align: right; }
        .grid td { padding: 2px 8px 2px 0; vertical-align: top; }
        .label { color: #555; font-size: 8pt; text-transform: uppercase; }
        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 33%; text-align: center; padding-top: 30px; border-top: 1px solid #111; font-size: 8.5pt; }
        .voided { position: fixed; top: 40%; left: 15%; font-size: 70pt; color: rgba(200, 0, 0, 0.18); transform: rotate(-25deg); }
    </style>
</head>
<body>
    @if ($remito->status->value === 'voided')<div class="voided">ANULADO</div>@endif

    <table class="header">
        <tr>
            <td>
                <div class="title">REMITO</div>
                <div>N° <strong>{{ $remito->number }}</strong></div>
                <div class="muted">Documento no válido como factura</div>
            </td>
            <td style="text-align:right">
                <strong>{{ setting('company.name') }}</strong><br>
                @if (setting('company.cuit'))CUIT {{ \App\Rules\Cuit::format(setting('company.cuit')) }}<br>@endif
                {{ setting('company.address') }}<br>
                {{ setting('company.phone') }}
            </td>
            <td style="width:120px; text-align:right"><img src="{{ $qr }}" style="width:110px; height:110px"></td>
        </tr>
    </table>

    <div class="box">
        <table class="grid">
            <tr>
                <td><div class="label">Fecha</div>{{ fdate($remito->issued_at, true) }}</td>
                <td><div class="label">Carga</div>{{ $cargo?->number }}</td>
                <td><div class="label">Estado</div>{{ $remito->status->label() }}</td>
            </tr>
            <tr>
                <td><div class="label">Cliente</div>{{ $remito->client?->business_name ?? '—' }}</td>
                <td><div class="label">CUIT</div>{{ \App\Rules\Cuit::format($remito->client?->cuit) ?? '—' }}</td>
                <td><div class="label">Destino</div>{{ $remito->destination?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td><div class="label">Camión</div>{{ $remito->truck?->plate ?? '—' }} {{ $remito->truck ? trim($remito->truck->brand.' '.$remito->truck->model) : '' }}</td>
                <td><div class="label">Chofer</div>{{ $remito->driver?->full_name ?? '—' }}</td>
                <td><div class="label">DNI chofer</div>{{ $remito->driver?->dni ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead><tr><th>Variedad</th><th>Tamaño</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
        <tbody>
            @foreach ($remito->items as $item)
                <tr><td>{{ $item->variety?->name ?? 'Sin variedad' }}</td><td>{{ $item->size?->name ?? 'Sin tamaño' }}</td><td class="num">{{ num($item->crates) }}</td><td class="num">{{ num($item->kg, 2) }}</td></tr>
            @endforeach
            <tr><th colspan="2">TOTAL</th><th class="num">{{ num($remito->total_crates) }}</th><th class="num">{{ num($remito->total_kg, 2) }}</th></tr>
        </tbody>
    </table>

    @if ($remito->notes)<p><strong>Observaciones:</strong> {{ $remito->notes }}</p>@endif

    <table class="signatures">
        <tr><td>Despachó</td><td>Transportista</td><td>Recibió (firma, aclaración y DNI)</td></tr>
    </table>

    <p class="muted" style="margin-top:20px">Consulta: {{ $publicUrl }}</p>
</body>
</html>
