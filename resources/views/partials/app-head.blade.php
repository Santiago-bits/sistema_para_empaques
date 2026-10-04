@php
    // Color principal (Configuración → Empresa). Sólo se acepta #rrggbb: va dentro de un <style>.
    $brand = strtolower((string) setting('ui.primary_color', '#16a34a'));
    $brand = preg_match('/^#[0-9a-f]{6}$/', $brand) === 1 ? $brand : '#16a34a';
@endphp
{{-- App instalable (escritorio y celular): ficha, ícono y color de la barra. --}}
<link rel="manifest" href="{{ route('app.manifest') }}">
<meta name="theme-color" content="{{ $brand }}">
@if ($brand !== '#16a34a')
    {{-- Toda la interfaz usa la paleta «brand» (botones, menú, enlaces): se arma entera a partir del color elegido,
         más claro hacia el 50 y más oscuro hacia el 950. :root:root le gana al CSS compilado sin importar el orden. --}}
    <style>
        :root:root {
            --color-brand-50: color-mix(in oklab, {{ $brand }} 7%, white);
            --color-brand-100: color-mix(in oklab, {{ $brand }} 15%, white);
            --color-brand-200: color-mix(in oklab, {{ $brand }} 30%, white);
            --color-brand-300: color-mix(in oklab, {{ $brand }} 50%, white);
            --color-brand-400: color-mix(in oklab, {{ $brand }} 72%, white);
            --color-brand-500: color-mix(in oklab, {{ $brand }} 88%, white);
            --color-brand-600: {{ $brand }};
            --color-brand-700: color-mix(in oklab, {{ $brand }} 82%, black);
            --color-brand-800: color-mix(in oklab, {{ $brand }} 66%, black);
            --color-brand-900: color-mix(in oklab, {{ $brand }} 52%, black);
            --color-brand-950: color-mix(in oklab, {{ $brand }} 32%, black);
        }
    </style>
@endif
<meta name="application-name" content="{{ setting('company.name', 'Galpón de Empaque') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Galpón">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=2">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png?v=2">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=2">
