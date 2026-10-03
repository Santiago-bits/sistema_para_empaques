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
<body class="min-h-screen" x-data="{ sidebar: false, offline: false }" :class="sidebar && 'max-lg:overflow-hidden'"
      @keydown.escape.window="sidebar = false" @toggle-sidebar.window="sidebar = ! sidebar"
      @connection-lost.window="offline = true" @connection-restored.window="offline = false">

{{-- Aviso de conexión perdida: el operador no debe asumir que la operación se guardó. --}}
<div x-cloak x-show="offline" class="fixed inset-x-0 top-0 z-[60] flex items-center justify-center gap-2 bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-lg">
    <x-icon name="signal" class="size-4"/> Sin conexión con el servidor. Las operaciones no se están guardando.
</div>

<div class="flex min-h-screen">
    {{-- Sidebar --}}
    {{-- Fondo oscuro y desenfocado detrás del menú en celulares/tablets: no se ve el contenido de atrás. --}}
    <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-30 bg-stone-950/80 backdrop-blur-sm lg:hidden" @click="sidebar = false" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] -translate-x-full flex-col bg-stone-900 shadow-2xl transition-transform duration-200 lg:w-64 lg:translate-x-0 lg:shadow-none dark:bg-stone-950 dark:ring-1 dark:ring-white/5"
           :class="sidebar && 'translate-x-0'" aria-label="Menú principal">
        <div class="flex h-16 shrink-0 items-center border-b border-white/5 pr-2">
        <a href="{{ route('home') }}" class="flex min-w-0 flex-1 items-center gap-3 px-5">
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
            <button type="button" class="rounded-lg p-2 text-stone-400 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Cerrar menú">
                <x-icon name="x" class="size-5"/>
            </button>
        </div>
        <nav class="sidebar-scroll flex-1 space-y-5 overflow-y-auto overscroll-contain px-3 py-4">
            @foreach ($menu as $section)
                <div>
                    @if ($section['title'])
                        <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-stone-500 uppercase">{{ $section['title'] }}</p>
                    @endif
                    <div class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <a href="{{ route($item['route']) }}" @class(['nav-item', 'active' => $item['active']]) @click="sidebar = false" @if ($item['active']) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" class="size-[18px] shrink-0 {{ ($item['highlight'] ?? false) ? 'text-accent-400' : 'text-stone-400' }}"/>
                                <span class="truncate">{{ $item['label'] }}</span>
                                @if ($item['route'] === 'alerts.index' && $openAlertsCount > 0)
                                    <span class="ml-auto rounded-full bg-accent-500 px-1.5 text-[11px] font-semibold text-white">{{ $openAlertsCount }}</span>
                                @elseif ($fkey = $shortcuts['routeKeys'][$item['route']] ?? null)
                                    <span class="ml-auto hidden rounded border border-white/10 px-1 font-mono text-[10px] text-stone-500 lg:inline" title="Atajo de teclado">{{ $fkey }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
        {{-- El menú queda donde lo dejaste al cambiar de pantalla (antes volvía siempre arriba de todo). --}}
        <script>
            (function () {
                var nav = document.querySelector('.sidebar-scroll');
                if (!nav) return;
                var key = 'galpon.sidebar-scroll';
                try { var saved = sessionStorage.getItem(key); if (saved !== null) nav.scrollTop = parseInt(saved, 10) || 0; } catch (e) {}
                var active = nav.querySelector('.nav-item.active');
                if (active) {
                    var a = active.getBoundingClientRect(), n = nav.getBoundingClientRect();
                    if (a.top < n.top || a.bottom > n.bottom) active.scrollIntoView({ block: 'center' });
                }
                var save = function () { try { sessionStorage.setItem(key, String(nav.scrollTop)); } catch (e) {} };
                nav.addEventListener('scroll', save, { passive: true });
                nav.addEventListener('click', save);
            })();
        </script>
        <div class="border-t border-white/5 px-5 py-3 text-[11px] text-stone-500">
            v{{ config('galpon.version') }}
            @if ($envLabel)
                · <span class="font-semibold text-accent-400">{{ $envLabel }}</span>
            @endif
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-stone-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-stone-800 dark:bg-stone-900/90 no-print">
            <button type="button" class="btn btn-ghost -ml-2 p-2 lg:hidden" @click="sidebar = true" aria-label="Abrir menú">
                <x-icon name="menu"/>
            </button>

            @if (Route::has('search'))
                <form action="{{ route('search') }}" method="GET" class="relative min-w-0 flex-1 sm:max-w-md" role="search">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400"/>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar código, CUIT, patente…"
                           class="form-input pl-9" autocomplete="off" data-global-search aria-keyshortcuts="/ Control+K">
                </form>
            @endif

            <div class="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1">
                @if ($envLabel)
                    <span class="hidden rounded-md bg-accent-500/15 px-2 py-1 text-xs font-semibold text-accent-600 sm:inline dark:text-accent-400">{{ $envLabel }}</span>
                @endif

                @if ($usdRate ?? null)
                    <a href="{{ auth()->user()->can('treasury.view') ? route('exchange.index') : '#' }}"
                       class="hidden items-center gap-1.5 rounded-md px-2 py-1 text-xs hover:bg-stone-100 sm:flex dark:hover:bg-stone-800"
                       title="Cotización del dólar (vendedor) del {{ fdate($usdRate->date) }}">
                        <span class="font-semibold text-emerald-700 dark:text-emerald-400">US$</span>
                        <span class="font-semibold tabular-nums">{{ money($usdRate->sell) }}</span>
                        @unless ($usdRate->date->isToday())<span class="text-amber-600 dark:text-amber-400" aria-label="No es de hoy">•</span>@endunless
                    </a>
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
                        <span class="hidden text-left md:block">
                            <span class="block text-sm leading-tight font-medium">{{ $user->full_name }}</span>
                            <span class="block text-[11px] leading-tight text-stone-500">{{ $user->role?->name }}</span>
                        </span>
                        <x-icon name="chevron-down" class="hidden size-4 text-stone-400 sm:block"/>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-2 w-56 panel p-1 text-sm">
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">
                            <x-icon name="user-chart" class="size-4"/> Mi perfil
                        </a>
                        <a href="{{ route('help.shortcuts') }}" class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-stone-100 dark:hover:bg-stone-800">
                            <x-icon name="keyboard" class="size-4"/> Ayuda y atajos <kbd class="kbd ml-auto">?</kbd>
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

{{-- Ventana de atajos: tecla «?» en cualquier pantalla. --}}
<x-modal name="shortcuts" title="Atajos de teclado" max-width="max-w-4xl">
    <div class="max-h-[70vh] overflow-y-auto pr-1">
        @include('help._shortcuts', ['groups' => $shortcuts['groups']])
    </div>
    <div class="mt-4 flex items-center justify-between gap-3 border-t border-stone-200 pt-3 text-sm dark:border-stone-800">
        <span class="text-stone-500">Cerrar con <kbd class="kbd">Esc</kbd></span>
        <a href="{{ route('help.shortcuts') }}" class="link">Ver la pantalla de ayuda completa</a>
    </div>
</x-modal>

<script>
    window.galpon = {
        sounds: {{ \Illuminate\Support\Js::from(['success' => (bool) setting('production.sound_success'), 'error' => (bool) setting('production.sound_error'), 'duplicate' => (bool) setting('production.sound_duplicate')]) }},
        shortcuts: {{ \Illuminate\Support\Js::from($shortcuts['bindings']) }},
    };
</script>
@stack('scripts')
</body>
</html>
