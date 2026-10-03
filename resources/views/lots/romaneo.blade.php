<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <title>Romaneo lote {{ $lot->code }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, 'Segoe UI', sans-serif; font-size: 10pt; color: #111; background: #fff; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 10px; padding: 10px 14px; background: #f5f5f4; border-bottom: 1px solid #ddd; }
        .toolbar button, .toolbar a { padding: 8px 14px; border: 0; border-radius: 6px; background: #16a34a; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .toolbar a { background: #57534e; }
        .page { padding: 16px; max-width: 190mm; margin: 0 auto; }
        header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        h1 { font-size: 15pt; margin: 0 0 4px; }
        h2 { font-size: 11pt; margin: 16px 0 6px; }
        .muted { color: #555; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px 16px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; }
        th { font-size: 8.5pt; text-transform: uppercase; color: #444; border-bottom: 1.5px solid #111; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 700; border-top: 1.5px solid #111; border-bottom: 0; }
        .boxes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 8px; }
        .box { border: 1px solid #bbb; border-radius: 6px; padding: 6px 8px; }
        .box b { display: block; font-size: 13pt; }
        .signs { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; }
        .signs div { border-top: 1px solid #111; padding-top: 4px; text-align: center; }
        @media print { .toolbar { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <x-print-back :fallback="route('lots.show', $lot)"/>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
    <div class="page">
        <header>
            <div>
                <h1>{{ setting('company.name') }}</h1>
                <div class="muted">{{ setting('company.address') }}@if (setting('company.cuit')) · CUIT {{ setting('company.cuit') }}@endif</div>
            </div>
            <div style="text-align:right">
                <strong style="font-size:13pt">ROMANEO</strong><br>
                Lote <strong>{{ $lot->code }}</strong> · {{ fdate($lot->date) }}
            </div>
        </header>

        <div class="grid">
            <div><span class="muted">Productor:</span> <strong>{{ $lot->producer?->name }}</strong></div>
            <div><span class="muted">Propietario:</span> {{ $lot->owner?->name ?? '—' }}</div>
            <div><span class="muted">Temporada:</span> {{ $lot->season?->name ?? '—' }}</div>
            <div><span class="muted">Variedad declarada:</span> {{ $lot->variety?->name ?? '—' }}</div>
            <div><span class="muted">Origen / campo:</span> {{ trim(($lot->origin ?? '').' '.($lot->field ?? '')) ?: '—' }}</div>
            <div><span class="muted">Envase:</span> {{ $lot->containerType?->name ?? '—' }} @if ($lot->quantity) ({{ num($lot->quantity) }})@endif</div>
        </div>

        <div class="boxes">
            <div class="box"><span class="muted">Kg recibidos</span><b>{{ $lot->kg_received !== null ? kg($lot->kg_received, 0) : '—' }}</b></div>
            <div class="box"><span class="muted">Kg empacados</span><b>{{ kg($summary['processed_kg'], 0) }}</b>{{ num($summary['records']) }} cajones</div>
            <div class="box"><span class="muted">Kg descarte</span><b>{{ kg($summary['rejected_kg'], 0) }}</b>{{ $waste_pct !== null ? pct($waste_pct).' de lo recibido' : '' }}</div>
            <div class="box"><span class="muted">Rinde de empaque</span><b>{{ $yield_pct !== null ? pct($yield_pct) : '—' }}</b>empacado / recibido</div>
        </div>

        <h2>Empacado por variedad, calibre y selección</h2>
        <table>
            <thead><tr><th>Variedad</th><th>Calibre</th><th>Selección</th><th class="num">Cajones</th><th class="num">Kg</th><th class="num">% del empacado</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->variety }}</td><td>{{ $row->size }}</td><td>{{ $row->grade ?? '—' }}</td>
                        <td class="num">{{ num($row->crates) }}</td><td class="num">{{ num($row->kg, 2) }}</td>
                        <td class="num">{{ $summary['processed_kg'] > 0 ? pct($row->kg / $summary['processed_kg'] * 100) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Todavía no se empacó fruta de este lote.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td colspan="3">Total empacado</td><td class="num">{{ num($summary['records']) }}</td><td class="num">{{ num($summary['processed_kg'], 2) }}</td><td></td></tr></tfoot>
        </table>

        @if ($rejects->isNotEmpty())
            <h2>Descarte por motivo</h2>
            <table>
                <thead><tr><th>Motivo</th><th class="num">Registros</th><th class="num">Kg</th></tr></thead>
                <tbody>
                    @foreach ($rejects as $r)
                        <tr><td>{{ $r->reason ?? 'Sin motivo' }}</td><td class="num">{{ num($r->total) }}</td><td class="num">{{ num($r->kg, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($lot->price_per_kg !== null && $lot->kg_received !== null)
            <p style="margin-top:12px">Precio de compra: {{ money($lot->price_per_kg) }} por kg · Importe: <strong>{{ money($lot->purchaseAmount()) }}</strong>
                {{ $lot->settled_at ? '· Liquidado el '.fdate($lot->settled_at) : '· Sin liquidar' }}</p>
        @endif

        <div class="signs"><div>Firma del empaque</div><div>Firma del productor</div></div>
        <p class="muted" style="margin-top:12px">Emitido el {{ now()->format('d/m/Y H:i') }}.</p>
    </div>
</body>
</html>
