{{-- Contenedor de tabla con scroll horizontal en pantallas chicas. --}}
<div {{ $attributes->merge(['class' => 'panel overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="table">
            {{ $slot }}
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-stone-200 px-4 py-3 dark:border-stone-800">{{ $footer }}</div>
    @endisset
</div>
