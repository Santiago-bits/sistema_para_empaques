<x-layouts.app title="Módulos del sistema">
    <x-page-header title="Módulos del sistema"
                   subtitle="Desactivar un módulo lo oculta del menú y bloquea su acceso, pero no borra ningún dato. Se puede reactivar en cualquier momento."/>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modules as $module)
            <div class="panel flex items-start justify-between gap-4 p-4">
                <div>
                    <p class="font-medium text-stone-900 dark:text-white">{{ $module->name }}</p>
                    <p class="mt-0.5 text-sm text-stone-500">{{ $module->description }}</p>
                    <p class="mt-2">
                        @if ($module->is_core)
                            <x-badge>Núcleo</x-badge>
                        @elseif ($module->enabled)
                            <x-badge color="emerald">ACTIVADO</x-badge>
                        @else
                            <x-badge color="zinc">DESACTIVADO</x-badge>
                        @endif
                    </p>
                </div>
                @unless ($module->is_core)
                    <form method="POST" action="{{ route('admin.modules.update', $module) }}"
                          x-data x-confirm="{{ $module->enabled ? '¿Desactivar el módulo '.$module->name.'? Los datos se conservan.' : '¿Activar el módulo '.$module->name.'?' }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="enabled" value="{{ $module->enabled ? 0 : 1 }}">
                        <button type="submit" role="switch" aria-checked="{{ $module->enabled ? 'true' : 'false' }}" aria-label="Cambiar estado de {{ $module->name }}"
                                @class(['relative inline-flex h-6 w-11 shrink-0 rounded-full transition', 'bg-brand-600' => $module->enabled, 'bg-stone-300 dark:bg-stone-700' => ! $module->enabled])>
                            <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition', 'left-[22px]' => $module->enabled, 'left-0.5' => ! $module->enabled])></span>
                        </button>
                    </form>
                @endunless
            </div>
        @endforeach
    </div>
</x-layouts.app>
