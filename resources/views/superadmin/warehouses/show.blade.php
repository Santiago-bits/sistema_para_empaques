<x-layouts.app :title="$warehouse->name">
    <x-page-header :title="$warehouse->name" :subtitle="$warehouse->code.($settings['locality'] ? ' · '.$settings['locality'] : '')" :back="route('superadmin.warehouses.index')">
        <x-slot:actions>
            <x-badge :color="$warehouse->active ? 'emerald' : 'red'" class="text-sm">{{ $warehouse->active ? 'Activo' : 'Suspendido' }}</x-badge>
            <form method="POST" action="{{ route('warehouses.enter', $warehouse) }}">
                @csrf
                <button class="btn btn-secondary"><x-icon name="logout" class="size-4"/> Entrar a este galpón</button>
            </form>
            <a href="{{ route('superadmin.warehouses.edit', $warehouse) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
        </x-slot:actions>
    </x-page-header>

    @include('superadmin._nav')

    @php
        $steps = [
            ['Galpón creado', true, null],
            ['Administrador general creado', $admins->isNotEmpty(), 'Crealo abajo: él va a cargar a sus empleados.'],
            ['El administrador entró y cambió su contraseña', $admins->contains(fn ($a) => $a->last_login_at && ! $a->must_change_password), 'Pasale su usuario y la contraseña temporal.'],
            ['Cargó a sus empleados', $usersCount > $admins->count(), 'Lo hace él desde Sistema → Usuarios.'],
        ];
    @endphp
    <x-panel title="Puesta en marcha" class="mb-6">
        <ol class="grid gap-3 md:grid-cols-4">
            @foreach ($steps as $i => [$label, $done, $help])
                <li class="flex gap-3">
                    <span @class(['grid size-7 shrink-0 place-items-center text-sm font-semibold', 'bg-brand-600 text-white' => $done, 'bg-stone-200 text-stone-600 dark:bg-stone-800 dark:text-stone-300' => ! $done])>
                        @if ($done)<x-icon name="check" class="size-4"/>@else{{ $i + 1 }}@endif
                    </span>
                    <span class="text-sm"><span class="font-medium">{{ $label }}</span>
                        @if (! $done && $help)<span class="block text-xs text-stone-500">{{ $help }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </x-panel>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Administrador general del galpón" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('superadmin.users.index', ['warehouse' => $warehouse->id]) }}" class="btn btn-secondary btn-sm"><x-icon name="users" class="size-4"/> Ver todos sus usuarios ({{ num($usersCount) }})</a>
                </x-slot:actions>
                <table class="table">
                    <thead><tr><th>Usuario</th><th>Nombre</th><th>Último ingreso</th><th>Contraseña</th></tr></thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr>
                                <td class="code">{{ $admin->username }}</td>
                                <td>{{ $admin->full_name }}</td>
                                <td class="text-stone-500">{{ $admin->last_login_at ? fdate($admin->last_login_at, true) : 'Nunca' }}</td>
                                <td>@if ($admin->must_change_password)<x-badge color="amber">Temporal</x-badge>@else<x-badge color="emerald">Propia</x-badge>@endif</td>
                            </tr>
                        @empty
                            <x-empty colspan="4" message="Todavía no tiene administrador."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>

            <x-panel title="Crear administrador general">
                <form method="POST" action="{{ route('superadmin.warehouses.admin', $warehouse) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-input name="first_name" label="Nombre" required/>
                        <x-input name="last_name" label="Apellido" required/>
                        <x-input name="username" label="Usuario" required class="code" hint="Con este usuario entra al sistema."/>
                        <x-input name="email" type="email" label="Email (opcional)" hint="Para que pueda recuperar su contraseña."/>
                        <x-input name="password" label="Contraseña temporal" :value="$password" required class="code" hint="Al entrar la primera vez el sistema le pide cambiarla."/>
                    </div>
                    <p class="text-sm text-stone-600 dark:text-stone-400">
                        Tiene acceso completo a <strong>su</strong> galpón: carga a sus empleados (Sistema → Usuarios), sus fichas, su configuración (nombre,
                        CUIT, logo, código ARCA, numeraciones) y sus módulos. No ve nada de otros galpones.
                    </p>
                    <div class="flex justify-end"><button class="btn btn-primary"><x-icon name="plus" class="size-4"/> Crear administrador</button></div>
                </form>
            </x-panel>
        </div>

        <x-panel title="Datos">
            <x-dl :items="[
                'Código' => $warehouse->code,
                'CUIT' => $settings['cuit'] ? \App\Rules\Cuit::format($settings['cuit']) : null,
                'Código ARCA del galpón' => $settings['arca'] ?: null,
                'Dirección' => $warehouse->address,
                'Usuarios' => num($usersCount),
                'Cajones (30 días)' => num($crates30),
                'Creado' => fdate($warehouse->created_at, true),
            ]"/>
            <p class="form-hint mt-3">El CUIT, el logo y el código ARCA los maneja el administrador del galpón desde su Configuración.</p>
        </x-panel>
    </div>
</x-layouts.app>
