@props(['label', 'name', 'checked' => false, 'value' => '1', 'hint' => null])
<label class="inline-flex cursor-pointer items-start gap-2.5 text-sm">
    @if (! $attributes->has('no-hidden'))
        <input type="hidden" name="{{ $name }}" value="0">
    @endif
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old(str_replace(['[', ']'], ['.', ''], $name), $checked))
           {{ $attributes->except('no-hidden')->merge(['class' => 'mt-0.5 size-4 rounded border-stone-300 text-brand-600 focus:ring-brand-500 dark:border-stone-600 dark:bg-stone-900']) }}>
    <span>
        <span class="font-medium text-stone-800 dark:text-stone-200">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-stone-500">{{ $hint }}</span>
        @endif
    </span>
</label>
