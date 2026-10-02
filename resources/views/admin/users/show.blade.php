<x-layouts.app :title="$user->full_name">
    <x-page-header :title="$user->full_name" :subtitle="$user->role?->name" :back="route('admin.users.index')">
        <x-slot:actions>
            @can('managePermissions', $user)
                <a href="{{ route('admin.users.permissions', $user) }}" class="btn btn-secondary"><x-icon name="shield" class="size-4"/> Permisos individuales</a>
            @endcan
            @can('update', $user)
                @unless ($user->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.password.reset', $user) }}" x-data x-confirm="¿Asignar una contraseña temporal a {{ $user->full_name }}? Se cerrarán sus sesiones y deberá cambiarla al ingresar.">
                        @csrf
                        <button class="btn btn-secondary"><x-icon name="key" class="size-4"/> Restablecer contraseña</button>
                    </form>
                @endunless
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('temporary_password'))
        <div class="panel mb-6 border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40" role="alert" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Contraseña temporal de {{ $user->username }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <code class="code rounded-lg bg-white px-3 py-2 text-xl tracking-wider text-stone-900 select-all dark:bg-stone-900 dark:text-white" x-ref="pw">{{ session('temporary_password') }}</code>
                <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard?.writeText($refs.pw.textContent.trim()); copied = true" x-text="copied ? 'Copiada' : 'Copiar'">Copiar</button>
            </div>
            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">Dásela en persona. Se muestra sólo esta vez y el sistema le pedirá cambiarla en su próximo ingreso.</p>
        </div>
    @endif

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
                'Contraseña' => $user->must_change_password ? 'Temporal (debe cambiarla al ingresar)' : ($user->password_changed_at ? 'Cambiada el '.fdate($user->password_changed_at, true) : null),
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
