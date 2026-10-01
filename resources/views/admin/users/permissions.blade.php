<x-layouts.app title="Permisos individuales">
    <x-page-header :title="'Permisos de '.$user->full_name" :back="route('admin.users.show', $user)"
                   subtitle="Por defecto el usuario hereda los permisos de su rol. Acá podés conceder o quitar permisos puntuales."/>

    <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @foreach ($permissions as $module => $items)
            <x-panel :title="\App\Services\ModuleService::CATALOG[$module][0] ?? $module" :padding="false">
                <table class="table">
                    <thead><tr><th>Permiso</th><th class="w-32">Rol</th><th class="w-72">Excepción individual</th></tr></thead>
                    <tbody>
                        @foreach ($items as $permission)
                            @php $mode = $overrides[$permission->slug] ?? 'inherit'; @endphp
                            <tr>
                                <td>{{ $permission->name }} <span class="code text-xs text-stone-400">{{ $permission->slug }}</span></td>
                                <td>
                                    @if ($user->isSuperAdmin() || in_array($permission->slug, $rolePermissions, true))
                                        <x-badge color="emerald">Sí</x-badge>
                                    @else
                                        <x-badge color="zinc">No</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="inline-flex overflow-hidden rounded-lg border border-stone-300 text-xs dark:border-stone-700">
                                        @foreach (['inherit' => 'Heredar', 'grant' => 'Conceder', 'revoke' => 'Quitar'] as $value => $label)
                                            <label class="cursor-pointer px-3 py-1.5 has-[:checked]:bg-brand-600 has-[:checked]:text-white">
                                                <input type="radio" class="sr-only" name="overrides[{{ $permission->slug }}]" value="{{ $value }}" @checked($mode === $value)>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-panel>
        @endforeach
        <div class="sticky bottom-4 flex justify-end">
            <button class="btn btn-primary shadow-lg">Guardar permisos</button>
        </div>
    </form>
</x-layouts.app>
