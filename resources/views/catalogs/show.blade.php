@php
    $canManage = auth()->user()->can($definition->managePermission());
    $items = [];
    foreach ($definition->fields() as $field) {
        $items[$field->label] = $field->format($record);
    }
@endphp
<x-layouts.app :title="$definition->recordLabel($record)">
    <x-page-header :title="$definition->recordLabel($record)" :subtitle="ucfirst($definition->singular())" :back="$definition->route('index')">
        <x-slot:actions>
            @if ($canManage && $definition->hasToggle())
                <form method="POST" action="{{ $definition->route('toggle', $record->getKey()) }}" x-data x-confirm="¿{{ $definition->toggleLabel($record) }}?">
                    @csrf @method('PATCH')
                    <button class="btn btn-secondary">{{ $definition->toggleLabel($record) }}</button>
                </form>
            @endif
            @if ($canManage)
                <a href="{{ $definition->route('edit', $record->getKey()) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endif
            {{ $extraActions ?? '' }}
            @if (array_key_exists($record->getMorphClass(), \App\Models\AccountMovement::HOLDERS) && auth()->user()->can('treasury.view') && Route::has('accounts.show'))
                <a href="{{ route('accounts.show', [$record->getMorphClass(), $record->getKey()]) }}" class="btn btn-secondary"><x-icon name="book" class="size-4"/> Cuenta corriente</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos">
                <x-dl :items="$items"/>
            </x-panel>
            {{ $slot ?? '' }}
            @yield('catalog-extra')
        </div>

        <x-panel title="Historial de cambios">
            @if ($history->isEmpty())
                <p class="text-sm text-stone-500">{{ auth()->user()->can('audit.view') ? 'Sin cambios registrados.' : 'Requiere permiso de auditoría.' }}</p>
            @else
                <ul class="space-y-3 text-sm">
                    @foreach ($history as $log)
                        <li>
                            <p class="font-medium">{{ __('audit.actions.'.$log->action) }}
                                @if ($log->action === 'update' && $log->new_values)
                                    <span class="font-normal text-stone-500">{{ collect($log->new_values)->keys()->map(fn ($k) => field_label($k))->join(', ') }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-stone-500 tabular-nums">{{ fdate($log->created_at, true) }} · {{ $log->user?->full_name ?? 'Sistema' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>
</x-layouts.app>
