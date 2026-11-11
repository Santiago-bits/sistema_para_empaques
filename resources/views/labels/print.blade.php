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
        /* Etiqueta: arriba la empresa; en el medio el código grande y los datos + el RECUADRO DE SEGURIDAD (peso en
           grande sobre un fondo rayado: si se borra o se raspa algo, se nota); abajo el código de barras ENTERO.
           Si los datos no entran, se achica la letra (script de abajo): el código de barras nunca se corta. */
        .label { width: {{ $width }}mm; height: {{ $height }}mm; border: 1px dashed #999; padding: 1.8mm 2.2mm; display: flex; flex-direction: column; gap: .7mm; overflow: hidden; page-break-after: always; break-after: page; }
        .company { flex: none; font-size: {{ $height < 45 ? 6 : 7 }}pt; font-weight: 700; line-height: 1.15; border-bottom: .3mm solid #000; padding-bottom: .4mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .associations { flex: none; font-size: 6pt; font-weight: 600; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .middle { flex: 1 1 auto; min-height: 0; display: flex; gap: 1.8mm; }
        .info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: .5mm; overflow: hidden; }
        .head { flex: none; display: flex; align-items: baseline; gap: 1.5mm; white-space: nowrap; overflow: hidden; }
        .kind { font-size: {{ $height < 45 ? 5.5 : 6.5 }}pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .code { font-family: Consolas, 'Courier New', monospace; font-size: {{ $width < 70 ? 12 : ($height >= 40 ? 16 : 13) }}pt; font-weight: 800; line-height: 1; }
        /* Datos en dos columnas; los que suelen ser largos (variedad, productor, destino) ocupan el renglón entero. */
        .lines { font-size: {{ $width < 70 ? 7 : 8.5 }}pt; line-height: 1.18; display: grid; grid-template-columns: {{ $width >= 90 ? '1fr 1fr' : '1fr' }}; column-gap: 2.5mm; }
        .lines div { white-space: nowrap; overflow: hidden; }
        .lines .wide { grid-column: 1 / -1; }
        .lines b { font-weight: 700; }
        .secure { flex: none; width: {{ $width >= 90 ? 33 : ($width >= 70 ? 28 : 24) }}mm; border: .6mm solid #000; padding: 1mm 1.2mm; display: flex; flex-direction: column; justify-content: center; text-align: center; gap: .4mm;
                  background: repeating-linear-gradient(45deg, #fff 0 .55mm, #d6d3d1 .55mm .75mm); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .secure .s-label { font-size: 6pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; background: #fff; align-self: center; padding: 0 1mm; }
        .secure .s-value { font-size: {{ $width < 70 ? 13 : 17 }}pt; font-weight: 800; line-height: 1.05; background: #fff; padding: .3mm .8mm; white-space: nowrap; }
        .secure .s-detail { font-size: {{ $width < 70 ? 6 : 7 }}pt; font-weight: 700; line-height: 1.15; background: #fff; align-self: center; padding: 0 .8mm; white-space: nowrap; }
        .secure .s-control { font-family: Consolas, 'Courier New', monospace; font-size: {{ $width < 70 ? 7 : 8 }}pt; font-weight: 800; letter-spacing: .12em; background: #000; color: #fff; align-self: center; padding: .2mm 1.2mm; }
        .qr { flex: none; align-self: center; }
        .qr svg { width: {{ min(22, (int) ($height * 0.45)) }}mm; height: {{ min(22, (int) ($height * 0.45)) }}mm; display: block; }
        .barcode { flex: none; text-align: center; }
        /* Barras a lo ancho (con margen blanco a los costados para que el lector las tome bien). */
        .barcode svg { width: calc(100% - 6mm); height: {{ max(6, (int) ($height * 0.18)) }}mm; display: block; margin: 0 auto; }
        .barcode-text { font-family: Consolas, 'Courier New', monospace; font-size: {{ $height < 45 ? 6.5 : 7.5 }}pt; font-weight: 700; letter-spacing: .15em; line-height: 1.1; }
        .regulatory { flex: none; font-size: {{ $height < 45 ? 4.8 : 5.8 }}pt; line-height: 1.12; text-align: center; border-top: .3mm solid #000; padding-top: .4mm; }
        /* Etiquetas chiquitas (menos de 55×38 mm): sólo código, peso con control y código de barras. */
        .compact .company, .compact .associations, .compact .lines, .compact .s-label, .compact .s-detail { display: none; }
        .compact .middle { flex-direction: column; gap: .5mm; }
        .compact .secure { width: auto; flex-direction: row; justify-content: space-between; align-items: center; padding: .4mm 1mm; border-width: .4mm; }
        .compact .secure .s-value { font-size: 10pt; }
        .compact .secure .s-control { font-size: 6.5pt; }
        .compact .regulatory { font-size: 4.2pt; }
        @media print {
            .toolbar { display: none; }
            .labels { padding: 0; gap: 0; }
            .label { border: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <x-print-back :fallback="$back"/>
        <button type="button" onclick="window.print()">Imprimir</button>
        <span>{{ $title }} · {{ count($labels) }} etiqueta(s) de {{ $width }}×{{ $height }} mm</span>
    </div>
    <div class="labels">
        @foreach ($labels as $label)
            <div @class(['label', 'compact' => $width < 55 || $height < 38])>
                @if (! empty($companyLine))<div class="company">{{ $companyLine }}</div>@endif
                @if (! empty($associationsLine))<div class="associations">{{ $associationsLine }}</div>@endif
                <div class="middle">
                    <div class="info">
                        <div class="head">
                            <span class="kind">{{ $label['title'] }}</span>
                            <span class="code">{{ $label['code'] }}</span>
                        </div>
                        <div class="lines">
                            @foreach ($label['lines'] as [$name, $value])
                                <div @class(['wide' => in_array($name, ['Variedad', 'Productor', 'Destino', 'Propietario'], true)])><b>{{ $name }}:</b> {{ $value }}</div>
                            @endforeach
                        </div>
                    </div>
                    @if (! empty($label['security']))
                        <div class="secure">
                            <span class="s-label">{{ $label['security']['label'] }}</span>
                            <span class="s-value">{{ $label['security']['value'] }}</span>
                            @foreach ($label['security']['details'] as $detail)<span class="s-detail">{{ $detail }}</span>@endforeach
                            @if (! empty($label['control']))<span class="s-control" title="Código de control">CTRL {{ $label['control'] }}</span>@endif
                        </div>
                    @else
                        <div class="qr">{!! $label['qr_svg'] !!}</div>
                    @endif
                </div>
                <div class="barcode">{!! str_replace('<svg ', '<svg preserveAspectRatio="none" ', $label['barcode_svg']) !!}<div class="barcode-text">{{ $label['barcode_text'] ?? $label['code'] }}</div></div>
                @if (! empty($regulatoryLine))<div class="regulatory">{{ $regulatoryLine }}</div>@endif
            </div>
        @endforeach
    </div>
    <script>
        // Nada se corta: el código del cajón y los datos se achican de a poco hasta entrar enteros.
        const overflows = (el) => el.scrollWidth > el.clientWidth + 1;
        document.querySelectorAll('.label').forEach((label) => {
            const info = label.querySelector('.info'), code = label.querySelector('.code'), lines = label.querySelector('.lines'), head = label.querySelector('.head');
            let c = parseFloat(getComputedStyle(code).fontSize);
            while (overflows(head) && c > 9) { c -= 0.5; code.style.fontSize = c + 'px'; }
            let size = parseFloat(getComputedStyle(lines).fontSize);
            const tooBig = () => info.scrollHeight > info.clientHeight + 1 || [...lines.children].some(overflows);
            while (tooBig() && size > 7.5) { size -= 0.25; lines.style.fontSize = size + 'px'; }
            // Etiquetas muy chicas: si igual no entra, se dejan afuera los datos menos importantes (los últimos).
            const visible = () => [...lines.children].filter((d) => d.style.display !== 'none');
            while (tooBig() && visible().length > 2) visible().at(-1).style.display = 'none';
        });
    </script>
</body>
</html>
