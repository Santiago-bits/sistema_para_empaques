@php
    $labels = ['username' => 'usuario', 'dni' => 'DNI', 'cuit' => 'CUIT', 'internal_code' => 'código interno', 'email' => 'email'];
    $hint = collect($identifiers)->map(fn ($i) => $labels[$i] ?? $i)->join(', ', ' o ');
@endphp
<x-layouts.guest title="Ingresar">
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
            <form method="POST" action="{{ route('login.store') }}" class="w-full max-w-sm space-y-5">
                @csrf
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Ingresar al sistema</h1>
                    <p class="mt-1 text-sm text-stone-500">Usá tu {{ $hint }}.</p>
                </div>

                @if (session('error'))
                    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ session('error') }}</div>
                @endif

                <x-input name="login" label="Usuario" autofocus autocomplete="username" required class="py-2.5 text-base"/>
                <x-input name="password" type="password" label="Contraseña" autocomplete="current-password" required class="py-2.5 text-base"/>
                <x-checkbox name="remember" label="Mantener la sesión iniciada en esta computadora" no-hidden/>

                <button type="submit" class="btn btn-primary w-full py-3 text-base">Ingresar</button>
            </form>
        </div>
    </div>
</x-layouts.guest>
