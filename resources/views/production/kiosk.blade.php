<x-layouts.kiosk title="Escaneo">
    <header class="flex items-center justify-between gap-4 border-b border-white/10 px-6 py-3">
        <div class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-600 font-bold">E</span>
            <div>
                <p class="font-semibold">{{ setting('company.name') }}</p>
                <p class="text-xs text-stone-400">Puesto de escaneo · {{ auth()->user()->full_name }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="hidden font-mono text-2xl tabular-nums text-stone-300 sm:block" x-data="{ t: '' }"
                  x-init="const tick = () => t = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false }); tick(); setInterval(tick, 10000)" x-text="t"></span>
            @unless (auth()->user()->kiosk_mode)
                <a href="{{ route('production.scan') }}" class="btn btn-secondary">Salir de pantalla completa</a>
            @endunless
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary"><x-icon name="logout" class="size-4"/> Salir</button>
            </form>
        </div>
    </header>
    <main class="p-4 sm:p-6">
        @include('production._scanner', ['kiosk' => true])
    </main>
</x-layouts.kiosk>
