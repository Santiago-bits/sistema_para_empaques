<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <title>Resumen de cuenta · {{ $label }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, 'Segoe UI', sans-serif; font-size: 10pt; color: #111; background: #fff; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 10px; padding: 10px 14px; background: #f5f5f4; border-bottom: 1px solid #ddd; }
        .toolbar button { padding: 8px 14px; border: 0; border-radius: 6px; background: #16a34a; color: #fff; font-weight: 600; cursor: pointer; }
        .page { padding: 16px; max-width: 190mm; margin: 0 auto; }
        header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        h1 { font-size: 15pt; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { font-size: 8.5pt; text-transform: uppercase; color: #444; border-bottom: 1.5px solid #111; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 700; border-top: 1.5px solid #111; border-bottom: 0; }
        .balance { font-size: 13pt; font-weight: 700; }
        @media print { .toolbar { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><x-print-back :fallback="route('accounts.index')"/><button type="button" onclick="window.print()">Imprimir</button></div>
    <div class="page">
        <header>
            <div>
                <h1>{{ setting('company.name') }}</h1>
                <div class="muted">{{ setting('company.address') }}@if (setting('company.cuit')) · CUIT {{ setting('company.cuit') }}@endif</div>
            </div>
            <div style="text-align:right">
                <strong>Resumen de cuenta corriente</strong><br>
                <span class="muted">Del {{ fdate($from) }} al {{ fdate($to) }}</span>
            </div>
        </header>

        <p><strong>{{ \App\Models\AccountMovement::HOLDERS[$type][0] }}:</strong> {{ $label }}@if ($holder->cuit) · CUIT {{ $holder->cuit }}@endif</p>

        <table>
            <thead><tr><th>Fecha</th><th>Detalle</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th></tr></thead>
            <tbody>
                <tr><td>{{ fdate($from) }}</td><td>Saldo anterior</td><td></td><td></td><td class="num">{{ money($statement['previous']) }}</td></tr>
                @foreach ($statement['rows'] as $row)
                    <tr>
                        <td>{{ fdate($row->date) }}</td>
                        <td>{{ $row->description }}</td>
                        <td class="num">{{ (float) $row->debit ? money($row->debit) : '' }}</td>
                        <td class="num">{{ (float) $row->credit ? money($row->credit) : '' }}</td>
                        <td class="num">{{ money($row->running_balance) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="2">Totales del período</td><td class="num">{{ money($statement['debit']) }}</td><td class="num">{{ money($statement['credit']) }}</td><td class="num">{{ money($statement['balance']) }}</td></tr>
            </tfoot>
        </table>

        <p class="balance" style="margin-top:14px">
            Saldo al {{ fdate($to) }}: {{ money(abs($statement['balance'])) }}
            {{ $statement['balance'] > 0.004 ? '(a favor de '.setting('company.name').')' : ($statement['balance'] < -0.004 ? '(a favor de '.$label.')' : '(cuenta saldada)') }}
        </p>
        <p class="muted">Emitido el {{ now()->format('d/m/Y H:i') }}.</p>
    </div>
</body>
</html>
