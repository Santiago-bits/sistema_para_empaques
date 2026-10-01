@props(['colspan' => 20, 'message' => 'No hay registros para mostrar.'])
<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-12 text-center text-sm text-stone-500 dark:text-stone-400">
        <x-icon name="archive" class="mx-auto mb-2 size-8 text-stone-300 dark:text-stone-600"/>
        {{ $message }}
    </td>
</tr>
