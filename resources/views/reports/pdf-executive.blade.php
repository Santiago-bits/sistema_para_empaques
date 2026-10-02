@php use App\Services\Reports\Format; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #111; }
        .title { font-size: 18pt; font-weight: bold; }
        .muted { color: #555; font-size: 8pt; }
        h2 { font-size: 11pt; margin: 16px 0 6px; border-bottom: 1px solid #111; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        .num { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">Reporte ejecutivo</div>
    <div class="muted">{{ $company['name'] }} · @foreach ($filters as $label => $value){{ $label }}: {{ $value }} · @endforeach Generado {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy }}</div>
    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            @foreach ($section['items'] as [$label, $value, $type])
                <tr><td>{{ $label }}</td><td class="num">{{ Format::display($value, $type) }}</td></tr>
            @endforeach
        </table>
    @endforeach
</body>
</html>
