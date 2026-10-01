@props(['title' => null])
@php
    $user = auth()->user();
    $companyName = setting('company.name', 'Galpón de Empaque');
    $envLabel = setting('system.environment_label') ?: (app()->environment('production') ? null : strtoupper(app()->environment()));
    $licenseStatus = $license?->effectiveStatus();
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="{{ $user->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="heartbeat" content="1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $companyName }}</title>
    <script>
        (function () {
            var m = document.documentElement.dataset.theme || 'system';
            var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen" x-data="{ sidebar: false, offline: false }"
      @connection-lost.window="offline = true" @connection-restored.window="offline = false">

{{-- Aviso de conexión perdida: el operador no debe asumir que la operación se guardó. --}}
<div x-cloak x-show="offline" class="fixed inset-x-0 top-0 z-[60] flex items-center justify-center gap-2 bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-lg">
    <x-icon name="signal" class="size-4"/> Sin conexión con el servidor. Las operaciones no se están guardando.
</div>

<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <div x-cloak x-show="sidebar" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebar = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-stone-900 transition-transform lg:translate-x-0 dark:bg-black/40 dark:ring-1 dark:ring-white/5"
           :class="sidebar && 'translate-x-0'">
        <a href="{{ route('home') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-5">
            @if (setting('company.logo'))
                <img src="{{ asset('storage/'.setting('company.logo')) }}" alt="" class="size-8 rounded object-contain">
            @else
                <span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow">
                    <svg viewBox="0 0 24 24" class="size-5" fill="currentColor"><circle cx="12" cy="13" r="7" opacity=".9"/><path d="M12 6c1-2.5 3-3.5 5-3.5-.5 2-2 3.5-5 3.5z" fill="#86efac"/></svg>
                </span>
            @endif
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-white">{{ $companyName }}</span>
                <span class="block text-[11px] text-stone-400">Gestión de empaque</span>
            </span>
        </a>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            @foreach ($menu as $section)
                <div>
                    @if ($section['title'])
                        <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ $section['title'] }}</p>
                    @endif
                    <div class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <a href="{{ route($item['route']) }}" @class(['nav-item', 'active' => $item['active']])>
                                <x-icon :name="$item['icon']" class="size-[18px] shrink-0 {{ ($item['highlight'] ?? false) ? 'text-accent-400' : 'text-stone-400' }}"/>
                                <span class="truncate">{{ $item['label'] }}</span>
                                @if ($item['route'] === 'alerts.index' && $openAlertsCount > 0)
                                    <span class="ml-auto rounded-full bg-accent-500 px-1.5 text-[11px] font-semibold text-white">{{ $openAlertsCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        <div class="border-t border-white/5 px-5 py-3 text-[11px] text-stone-500">
            v{{ config('galpon.version') }}
            @if ($envLabel)
                · <span class="font-semibold text-accent-400">{{ $envLabel }}</span>
            @endif
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-stone-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-stone-800 dark:bg-stone-900/90 no-print">
            <button type="button" class="btn btn-ghost -ml-2 p-2 lg:hidden" @click="sidebar = true" aria-label="Abrir menú">
                <x-icon name="menu"/>
            </button>

            @if (Route::has('search'))
                <form action="{{ route('search') }}" method="GET" class="relative w-full max-w-md" role="search">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400"/>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar cajón, pallet, carga, remito, CUIT, patente…"
                           class="form-input pl-9" autocomplete="off" x-ref="globalSearch"
                           @keydown.window.slash.prevent="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) $refs.globalSearch.focus()">
                </form>
            @endif

            <div class="ml-auto flex items-center gap-1">
                @if ($envLabel)
                    <span class="hidden rounded-md bg-accent-500/15 px-2 py-1 text-xs font-semibold text-accent-600 sm:inline dark:text-accent-400">{{ $envLabel }}</span>
                @endif

                {{-- Tema --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" class="btn btn-ghost p-2" @click="open = !open" aria-label="Tema">
                        <x-icon name="sun" class="size-5 dark:hidden"/>
                        <x-icon name="moon" class="hidden size-5 dark:block"/>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition
                         class="absolute right-0 mt-2 w-40 panel p-1 text-sm">
                        @foreach (['light' => ['Claro', 'sun'], 'dark' => ['Oscuro', 'moon'], 'system' => ['Sistema', 'computer']] as $mode => [$label, $icon])
                            <button type="button" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800"
                                    @click="theme.set('{{ $mode }}'); open = false">
                                <x-icon :name="$icon" class="size-4"/> {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Notificaciones --}}
                @if (Route::has('notifications.index'))
                    <a href="{{ route('notifications.index') }}" class="btn btn-ghost relative p-2" aria-label="Notificaciones">
                        <x-icon name="bell"/>
                        @if ($unreadCount > 0)
                            <span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                        @endif
                    </a>
                @endif

                {{-- Usuario --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800">
                        <span class="grid size-8 place-items-center rounded-full bg-brand-600/15 text-xs font-bold text-brand-700 dark:text-brand-300">
                            {{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm leading-tight font-medium">{{ $user->full_name }}</span>
                            <span class="block text-[11px] leading-tight text-stone-500">{{ $user->role?->name }}</span>
                        </span>
                        <x-icon name="chevron-down" class="size-4 text-stone-400"/>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-56 panel p-1 text-sm">
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">
                            <x-icon name="user-chart" class="size-4"/> Mi perfil
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40">
                                <x-icon name="logout" class="size-4"/> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        @if ($licenseStatus && $licenseStatus !== 'active')
            <div class="border-b border-amber-300 bg-amber-50 px-6 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <strong>Licencia {{ $licenseStatus === 'expired' ? 'vencida' : 'suspendida' }}.</strong>
                El sistema sigue funcionando y sus datos están seguros. Contactá a soporte para regularizarla.
            </div>
        @endif

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <x-flash/>
            {{ $slot }}
        </main>
    </div>
</div>

<script>
    window.galpon = {
        sounds: {{ \Illuminate\Support\Js::from(['success' => (bool) setting('production.sound_success'), 'error' => (bool) setting('production.sound_error'), 'duplicate' => (bool) setting('production.sound_duplicate')]) }},
    };
</script>
@stack('scripts')
</body>
</html>
