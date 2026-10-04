{{-- App instalable (escritorio y celular): ficha, ícono y color de la barra. --}}
<link rel="manifest" href="{{ route('app.manifest') }}">
<meta name="theme-color" content="#16a34a">
<meta name="application-name" content="{{ setting('company.name', 'Galpón de Empaque') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Galpón">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png?v=2">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png?v=2">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v=2">
