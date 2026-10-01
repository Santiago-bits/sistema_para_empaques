@props(['title' => 'Producción'])
{{-- Layout mínimo para PCs de producción: sin menú, alto contraste, pantalla completa. --}}
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="heartbeat" content="1">
    <title>{{ $title }} · {{ setting('company.name', 'Galpón de Empaque') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-white" x-data="{ offline: false }"
      @connection-lost.window="offline = true" @connection-restored.window="offline = false">
    <div x-cloak x-show="offline" class="fixed inset-x-0 top-0 z-[60] bg-red-600 py-3 text-center text-lg font-bold text-white">
        SIN CONEXIÓN CON EL SERVIDOR — no se están guardando registros
    </div>
    {{ $slot }}
    <script>
        window.galpon = {
            sounds: {{ \Illuminate\Support\Js::from(['success' => (bool) setting('production.sound_success'), 'error' => (bool) setting('production.sound_error'), 'duplicate' => (bool) setting('production.sound_duplicate')]) }},
        };
    </script>
    @stack('scripts')
</body>
</html>
