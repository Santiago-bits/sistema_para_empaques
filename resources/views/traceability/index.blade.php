<x-layouts.app title="Seguir un cajón">
    <x-page-header title="Seguir un cajón o pallet" subtitle="Escaneá o escribí el código de un cajón o pallet para ver toda su historia."/>

    <form method="GET" action="{{ route('traceability.index') }}" class="panel mb-6 flex flex-wrap items-end gap-3 p-4" data-allow-resubmit>
        <div class="min-w-64 flex-1">
            <label for="code" class="form-label">Código de cajón o pallet</label>
            {{-- Sólo el número = cajón («123» → CJ-000123); para un pallet, escribir PAL-… --}}
            <input id="code" name="code" value="{{ $code }}" autofocus class="form-input code py-3 text-lg" placeholder="Número de cajón (123) o PAL-…" {{ code_attrs('crate') }} data-code-addon="off" autocomplete="off">
        </div>
        <button class="btn btn-primary btn-lg"><x-icon name="search" class="size-5"/> Consultar</button>
    </form>

    @if ($notFound)
        <div class="panel p-8 text-center text-stone-500">No se encontró ningún cajón ni pallet con el código <span class="code">{{ $code }}</span>.</div>
    @elseif ($crateTrace)
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold">Cajón <span class="code">{{ $crateTrace['crate']->code }}</span></h2>
            <x-status :status="$crateTrace['crate']->status"/>
            <a href="{{ route('crates.show', $crateTrace['crate']) }}" class="link text-sm">Abrir ficha</a>
        </div>
        @include('traceability._trace', ['chain' => $crateTrace['chain'], 'timeline' => $crateTrace['timeline']])
    @elseif ($palletTrace)
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold">Pallet <span class="code">{{ $palletTrace['pallet']->code }}</span></h2>
            <x-status :status="$palletTrace['pallet']->status"/>
            @if (Route::has('pallets.show'))<a href="{{ route('pallets.show', $palletTrace['pallet']) }}" class="link text-sm">Abrir ficha</a>@endif
            <span class="text-sm text-stone-500">{{ num($palletTrace['summary']['crates']) }} cajones · {{ kg($palletTrace['summary']['kg']) }}</span>
        </div>
        @include('traceability._trace', ['chain' => $palletTrace['chain'], 'timeline' => $palletTrace['timeline']])
    @endif
</x-layouts.app>
