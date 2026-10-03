@props(['items' => []])
{{-- Lista de definición: ['Etiqueta' => 'valor', ...]. Los valores se escapan. --}}
<dl {{ $attributes->merge(['class' => 'grid grid-cols-[minmax(0,1fr)] gap-x-6 gap-y-3 text-sm sm:grid-cols-[repeat(2,minmax(0,1fr))]']) }}>
    @foreach ($items as $label => $value)
        <div class="min-w-0">
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">{{ $label }}</dt>
            <dd class="mt-0.5 [overflow-wrap:anywhere] text-stone-900 dark:text-stone-100">{{ ($value === null || $value === '') ? '—' : $value }}</dd>
        </div>
    @endforeach
    {{ $slot }}
</dl>
