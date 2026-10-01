<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <title>Credenciales de embaladores</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, "Segoe UI", Arial, sans-serif; margin: 0; color: #111; background: #fff; color-scheme: light; }
        .toolbar { padding: 12px; background: #f5f5f4; border-bottom: 1px solid #ddd; display: flex; gap: 8px; align-items: center; }
        .toolbar button { padding: 8px 14px; border: 0; border-radius: 6px; background: #16a34a; color: #fff; font-weight: 600; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(2, 85.6mm); gap: 6mm; padding: 6mm; justify-content: center; }
        .badge { background: #fff; width: 85.6mm; height: 54mm; border: 1px solid #222; border-radius: 3mm; padding: 4mm; display: flex; flex-direction: column; justify-content: space-between; page-break-inside: avoid; }
        .company { font-size: 8pt; text-transform: uppercase; letter-spacing: .08em; color: #555; }
        .name { font-size: 13pt; font-weight: 700; margin-top: 1mm; }
        .code { font-family: Consolas, monospace; font-size: 11pt; font-weight: 700; }
        .barcode svg { width: 100%; height: 16mm; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        <span>{{ $packers->count() }} credencial(es)</span>
    </div>
    <div class="sheet">
        @foreach ($packers as $item)
            <div class="badge">
                <div>
                    <div class="company">{{ setting('company.name') }} · Embalador</div>
                    <div class="name">{{ $item['packer']->full_name }}</div>
                    <div class="code">{{ $item['packer']->code }}</div>
                </div>
                <div class="barcode">{!! $item['barcode'] !!}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
