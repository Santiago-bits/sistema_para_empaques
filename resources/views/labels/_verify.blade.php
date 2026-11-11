{{-- Para comparar con la etiqueta pegada: si el peso o el código de control no coinciden, la etiqueta fue alterada. --}}
@php
    $isCrate = $item instanceof \App\Models\Crate;
    $control = app(\App\Services\LabelService::class)->controlCode($item);
    $value = $isCrate ? ($item->weight !== null ? kg($item->weight) : 'sin peso') : num($item->capacityLimit()).' cajones';
@endphp
<div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 border border-stone-300 bg-white px-4 py-3 text-sm dark:border-stone-700 dark:bg-stone-900" data-label-verify>
    <span class="font-semibold text-stone-900 dark:text-white">Verificar etiqueta:</span>
    <span>{{ $isCrate ? 'Peso' : 'Capacidad' }} <strong class="tabular-nums text-stone-900 dark:text-white">{{ $value }}</strong></span>
    <span>Control <strong class="code bg-stone-900 px-1.5 py-0.5 text-white dark:bg-white dark:text-stone-900">{{ $control }}</strong></span>
    <span class="text-xs text-stone-500 dark:text-stone-400">Si lo que dice la etiqueta pegada no coincide, fue modificada a mano.</span>
</div>
