@props(['target', 'continuous' => false, 'title' => null])
{{--
    Botón «Cámara» para escanear con el celular/tablet el código que va en el campo #target.
    Al leer, completa el campo y dispara Enter (igual que un lector USB). Con :continuous="true"
    la cámara queda abierta para leer varios códigos seguidos.
--}}
<button type="button" {{ $attributes->merge(['class' => 'btn btn-secondary shrink-0 gap-1.5 px-3']) }}
        onclick="window.scanInto(document.getElementById({{ \Illuminate\Support\Js::from($target) }}), {{ \Illuminate\Support\Js::from(['continuous' => (bool) $continuous, 'title' => $title]) }})"
        title="Escanear con la cámara" aria-label="Escanear con la cámara">
    <x-icon name="camera" class="size-5"/><span class="hidden sm:inline">Cámara</span>
</button>
