@php use App\Services\Reports\Format; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset->title }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5pt; color: #111; }
        .head { width: 100%; border-bottom: 2px solid #111; margin-bottom: 8px; }
        .title { font-size: 15pt; font-weight: bold; }
        .muted { color: #555; font-size: 7.5pt; }
        .filters { margin: 6px 0 10px; font-size: 8pt; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #eee; border: 1px solid #aaa; padding: 4px; text-align: left; }
        table.data td { border: 1px solid #ccc; padding: 3px 4px; }
        .num { text-align: right; }
        tr.total td { font-weight: bold; background: #f5f5f5; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7pt; color: #777; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><div class="title">{{ $dataset->title }}</div><div class="muted">{{ $company['name'] }} @if ($company['cuit'])· CUIT {{ \App\Rules\Cuit::format($company['cuit']) }}@endif</div></td>
            <td style="text-align:right" class="muted">Generado {{ $generatedAt->format('d/m/Y H:i') }}<br>{{ $generatedBy }}</td>
        </tr>
    </table>
    <div class="filters">@foreach ($filters as $label => $value)<strong>{{ $label }}:</strong> {{ $value }} &nbsp; @endforeach</div>

    <table class="data">
        <thead><tr>@foreach ($dataset->columns as $c)<th class="{{ in_array($c['type'], Format::NUMERIC, true) ? 'num' : '' }}">{{ $c['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($dataset->rows() as $row)
                <tr>@foreach ($dataset->columns as $k => $c)<td class="{{ in_array($c['type'], Format::NUMERIC, true) ? 'num' : '' }}">{{ Format::display($row[$k] ?? null, $c['type']) }}</td>@endforeach</tr>
            @endforeach
            @if ($dataset->totals)
                <tr class="total">@foreach ($dataset->columns as $k => $c)<td class="{{ in_array($c['type'], Format::NUMERIC, true) ? 'num' : '' }}">{{ array_key_exists($k, $dataset->totals) ? Format::display($dataset->totals[$k], $c['type']) : '' }}</td>@endforeach</tr>
            @endif
        </tbody>
    </table>
    <div class="footer">{{ $company['name'] }} · Sistema de gestión de empaque</div>
</body>
</html>
