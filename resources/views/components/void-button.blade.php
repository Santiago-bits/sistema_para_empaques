@props(['action', 'label' => 'Anular', 'title' => '¿Anular?'])
{{-- Anulación con motivo obligatorio, sin ventanas del navegador: el botón despliega el campo del motivo. --}}
<div x-data="{ open: false }" class="inline-block text-left" @keydown.escape.stop="open = false">
    <button type="button" class="text-sm text-red-600 hover:underline dark:text-red-400" @click="open = ! open; $nextTick(() => open && $refs.reason.focus())">{{ $label }}</button>
    <form x-cloak x-show="open" method="POST" action="{{ $action }}" class="mt-2 flex min-w-64 flex-col gap-2 rounded-lg border border-red-200 bg-red-50 p-2 dark:border-red-900 dark:bg-red-950/40">
        @csrf
        <span class="text-xs font-medium text-red-800 dark:text-red-200">{{ $title }}</span>
        <input x-ref="reason" name="reason" required minlength="5" maxlength="255" class="form-input py-1.5 text-sm" placeholder="Motivo (obligatorio)">
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancelar</button>
            <button class="btn btn-danger btn-sm">Confirmar</button>
        </div>
    </form>
</div>
