<x-layouts.app :title="$user->full_name">
    <x-page-header :title="$user->full_name" :subtitle="$user->role?->name" :back="route('admin.users.index')">
        <x-slot:actions>
            @can('managePermissions', $user)
                <a href="{{ route('admin.users.permissions', $user) }}" class="btn btn-secondary"><x-icon name="shield" class="size-4"/> Permisos individuales</a>
            @endcan
            @can('update', $user)
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Datos" class="lg:col-span-2">
            <x-dl :items="[
                'Usuario' => $user->username,
                'Estado' => $user->status->label(),
                'DNI' => $user->dni,
                'CUIT' => $user->cuit,
                'Código interno' => $user->internal_code,
                'Email' => $user->email,
                'Teléfono' => $user->phone,
                'Embalador vinculado' => $user->packer?->full_name,
                'Galpones' => $user->warehouses->pluck('name')->join(', '),
                'Modo kiosco' => $user->kiosk_mode ? 'Sí' : 'No',
                'Alta' => fdate($user->created_at, true),
                'Último acceso' => fdate($user->last_login_at, true).($user->last_login_ip ? ' · IP '.$user->last_login_ip : ''),
                'Fecha de baja' => fdate($user->deactivated_at, true),
                'Observaciones' => $user->notes,
            ]"/>
        </x-panel>

        <x-panel title="Actividad reciente">
            <ul class="space-y-3 text-sm">
                @forelse ($activity as $log)
                    <li>
                        <p class="font-medium">{{ __('audit.actions.'.$log->action) }} <span class="text-stone-500">{{ $log->auditable_type }} {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}</span></p>
                        <p class="text-xs text-stone-500 tabular-nums">{{ fdate($log->created_at, true) }} · {{ $log->ip_address }}</p>
                    </li>
                @empty
                    <li class="text-stone-500">Sin actividad registrada.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-layouts.app>
