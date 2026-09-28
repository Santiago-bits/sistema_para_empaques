{{-- Página independiente (sin menú) pensada para imprimir la etiqueta del empaque. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta {{ $empaque->codigo }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 24px 16px;
            background: #f1f3f5;
            color: #000;
        }

        .acciones {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .acciones button,
        .acciones a {
            font: inherit;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #212529;
            background: #212529;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }

        .acciones a {
            background: #fff;
            color: #212529;
        }

        .etiqueta {
            width: 6cm;
            margin: 0 auto;
            padding: 0.4cm;
            background: #fff;
            border: 1px dashed #adb5bd;
            text-align: center;
        }

        .etiqueta img {
            display: block;
            width: 100%;
            height: auto;
        }

        .codigo {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 16pt;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 0.15cm;
        }

        .nombre {
            font-size: 10pt;
            margin-top: 0.1cm;
            overflow-wrap: anywhere;
        }

        @media print {
            @page { margin: 1cm; }
            body { background: #fff; padding: 0; }
            .acciones { display: none; }
            .etiqueta { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="acciones">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('empaques.show', $empaque) }}">Volver</a>
    </div>

    <div class="etiqueta">
        <img src="{{ route('empaques.qr', [$empaque, 'svg']) }}" alt="QR {{ $empaque->codigo }}">
        <div class="codigo">{{ $empaque->codigo }}</div>
        <div class="nombre">{{ $empaque->nombre }}</div>
    </div>
</body>
</html>
