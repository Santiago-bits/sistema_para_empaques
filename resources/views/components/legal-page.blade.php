@props(['title', 'provider', 'version'])
{{-- Marco común de las páginas legales: encabezado, índice y pie con los datos del titular. --}}
@php
    $p = $provider;
    $holder = $p['name'].($p['cuit'] ? ' (CUIT '.$p['cuit'].')' : '');
@endphp
<x-layouts.guest :title="$title">
    <div class="min-h-screen bg-stone-50 dark:bg-stone-950">
        <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                <a href="{{ auth()->check() ? route('home') : route('login') }}" class="flex items-center gap-2 font-semibold text-stone-900 dark:text-white">
                    <x-logo class="size-8"/> {{ $p['name'] }}
                </a>
                <nav class="flex gap-4 text-sm">
                    <a href="{{ route('legal.terms') }}" @class(['link', 'font-semibold' => request()->routeIs('legal.terms')])>Términos y condiciones</a>
                    <a href="{{ route('legal.privacy') }}" @class(['link', 'font-semibold' => request()->routeIs('legal.privacy')])>Privacidad</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8">
            <h1 class="text-2xl font-semibold text-stone-900 sm:text-3xl dark:text-white">{{ $title }}</h1>
            <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">Versión {{ \Illuminate\Support\Carbon::parse($version)->format('d/m/Y') }} · Titular: {{ $holder }}</p>

            <div class="legal-text mt-8 space-y-6 text-[15px] leading-relaxed text-stone-700 dark:text-stone-300">
                {{ $slot }}
            </div>

            <footer class="mt-12 border-t border-stone-200 pt-6 text-sm text-stone-500 dark:border-stone-800 dark:text-stone-400">
                <p>{{ $holder }}@if ($p['address']) · {{ $p['address'] }}@endif</p>
                @if ($p['email'])<p>Contacto: <a class="link" href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a></p>@endif
            </footer>
        </main>
    </div>

    <style>
        .legal-text h2 { font-size: 1.125rem; font-weight: 600; margin-top: 2rem; color: inherit; }
        .legal-text h2 { color: rgb(28 25 23); }
        :root.dark .legal-text h2 { color: #fff; }
        .legal-text ul { list-style: disc; padding-left: 1.25rem; }
        .legal-text li + li { margin-top: .35rem; }
        .legal-text p + p { margin-top: .75rem; }
    </style>
</x-layouts.guest>
