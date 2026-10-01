@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])
{{-- Envoltorio de campo: etiqueta + control (slot) + error + ayuda. --}}
<div {{ $attributes->merge(['class' => '']) }}>
    @if ($label)
        <label @if ($name) for="{{ $name }}" @endif class="form-label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($name)
        @error($name) <p class="form-error">{{ $message }}</p> @enderror
    @endif
    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
