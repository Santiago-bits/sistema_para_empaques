@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
{{-- Abrir con: $dispatch('open-modal', 'nombre'). Cerrar con Escape o $dispatch('close-modal', 'nombre'). --}}
<div x-data="{ open: false }" x-cloak
     @open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     @close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     @keydown.escape.window="open = false"
     x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div x-show="open" x-transition class="relative w-full {{ $maxWidth }} panel shadow-xl">
        @if ($title)
            <div class="panel-header">
                <h3 class="panel-title">{{ $title }}</h3>
                <button type="button" @click="open = false" class="btn btn-ghost p-1" aria-label="Cerrar"><x-icon name="x" class="size-4"/></button>
            </div>
        @endif
        <div class="p-4">{{ $slot }}</div>
    </div>
</div>
