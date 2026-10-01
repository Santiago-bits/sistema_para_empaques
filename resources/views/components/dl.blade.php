@props(['items' => []])
{{-- Lista de definición: ['Etiqueta' => 'valor', ...]. Los valores se escapan. --}}
<dl {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2']) }}>
    @foreach ($items as $label => $value)
        <div>
            <dt class="text-xs font-medium tracking-wide text-stone-500 uppercase dark:text-stone-400">{{ $label }}</dt>
            <dd class="mt-0.5 text-stone-900 dark:text-stone-100">{{ ($value === null || $value === '') ? '—' : $value }}</dd>
        </div>
    @endforeach
    {{ $slot }}
</dl>
