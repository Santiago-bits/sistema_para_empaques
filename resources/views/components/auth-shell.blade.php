@props(['title'])
{{-- Pantallas de acceso (login, recuperar/restablecer/cambiar contraseña): panel de marca + formulario. --}}
<x-layouts.guest :title="$title">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-stone-900 lg:block">
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: repeating-linear-gradient(90deg, #fff 0 1px, transparent 1px 48px), repeating-linear-gradient(0deg, #fff 0 1px, transparent 1px 48px);"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-lg">
                        <svg viewBox="0 0 24 24" class="size-6" fill="currentColor"><circle cx="12" cy="13" r="7" opacity=".9"/><path d="M12 6c1-2.5 3-3.5 5-3.5-.5 2-2 3.5-5 3.5z" fill="#86efac"/></svg>
                    </span>
                    <span class="text-lg font-semibold">{{ setting('company.name', 'Galpón de Empaque') }}</span>
                </div>
                <div>
                    <p class="font-mono text-xs tracking-widest text-brand-400 uppercase">Productor → Lote → Pallet → Cajón → Carga → Remito → Factura</p>
                    <h2 class="mt-4 max-w-md text-4xl leading-tight font-semibold">Cada cajón, con su historia completa.</h2>
                    <p class="mt-4 max-w-md text-stone-400">Qué ingresó, de quién es, quién lo embaló, cuánto pesó, dónde está y en qué camión salió.</p>
                </div>
                <p class="text-xs text-stone-500">v{{ config('galpon.version') }}</p>
            </div>
        </div>

        <div class="flex items-center justify-center p-6">
            <div class="w-full max-w-sm space-y-5">
                @if (session('status'))
                    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200" role="alert">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status">{{ session('success') }}</div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.guest>
