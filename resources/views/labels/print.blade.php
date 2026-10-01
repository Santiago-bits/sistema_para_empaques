<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $width }}mm {{ $height }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #fff; color: #000; font-family: Arial, 'Segoe UI', sans-serif; color-scheme: light; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 10px; align-items: center; padding: 10px 14px; background: #f5f5f4; border-bottom: 1px solid #ddd; font-size: 14px; }
        .toolbar button, .toolbar a { padding: 8px 14px; border: 0; border-radius: 6px; background: #16a34a; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #57534e; }
        .labels { display: flex; flex-wrap: wrap; gap: 4mm; padding: 4mm; }
        .label { width: {{ $width }}mm; height: {{ $height }}mm; border: 1px dashed #999; padding: 2.5mm; display: grid; grid-template-columns: 1fr auto; grid-template-rows: auto 1fr auto; gap: 1mm 2mm; overflow: hidden; page-break-after: always; }
        .head { grid-column: 1 / 3; display: flex; justify-content: space-between; align-items: baseline; }
        .kind { font-size: 7pt; text-transform: uppercase; letter-spacing: .1em; color: #444; }
        .code { font-family: Consolas, 'Courier New', monospace; font-size: 13pt; font-weight: 700; }
        .lines { font-size: 7.5pt; line-height: 1.25; }
        .lines b { font-weight: 600; }
        .qr svg { width: {{ min(22, (int) ($height * 0.5)) }}mm; height: {{ min(22, (int) ($height * 0.5)) }}mm; display: block; }
        .barcode { grid-column: 1 / 3; }
        .barcode svg { width: 100%; height: {{ max(8, (int) ($height * 0.22)) }}mm; display: block; }
        @media print {
            .toolbar { display: none; }
            .labels { padding: 0; gap: 0; }
            .label { border: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        @if ($back)<a href="{{ $back }}">Volver</a>@endif
        <span>{{ $title }} · {{ count($labels) }} etiqueta(s) de {{ $width }}×{{ $height }} mm</span>
    </div>
    <div class="labels">
        @foreach ($labels as $label)
            <div class="label">
                <div class="head">
                    <span class="kind">{{ $label['title'] }}</span>
                    <span class="code">{{ $label['code'] }}</span>
                </div>
                <div class="lines">
                    @foreach ($label['lines'] as [$name, $value])
                        <div><b>{{ $name }}:</b> {{ $value }}</div>
                    @endforeach
                </div>
                <div class="qr">{!! $label['qr_svg'] !!}</div>
                <div class="barcode">{!! $label['barcode_svg'] !!}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
