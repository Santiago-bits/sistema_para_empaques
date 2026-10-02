<x-layouts.app :title="$item->code">
    <x-page-header :title="$item->code" :subtitle="fdate($item->created_at, true)" :back="route('developer.errors')"/>

    <x-panel class="mb-6">
        <x-dl :items="[
            'Categoría' => $item->category,
            'Excepción' => $item->exception,
            'Archivo' => $item->file ? $item->file.':'.$item->line : null,
            'URL' => $item->url,
            'Usuario' => $item->user?->full_name,
            'IP' => $item->ip_address,
        ]"/>
        <h3 class="mt-5 mb-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">Mensaje</h3>
        <p class="text-sm [overflow-wrap:anywhere]">{{ $item->message }}</p>
    </x-panel>

    @if ($item->trace)
        <x-panel title="Traza" :padding="false">
            <pre class="max-h-[60vh] overflow-auto p-4 font-mono text-xs leading-relaxed text-stone-700 dark:text-stone-300">{{ \App\Support\LogReader::mask($item->trace) }}</pre>
        </x-panel>
    @endif
</x-layouts.app>
