<x-layouts.app :title="'Administración general · '.$user->full_name">
    <x-page-header :title="$user->full_name" :subtitle="($user->role?->name ?? 'Sin rol').' · '.$user->username" :back="route('superadmin.users.index')"/>

    @include('superadmin._nav')

    @if (session('temporary_password'))
        <div class="panel mb-6 border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40" role="alert" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Contraseña temporal de {{ $user->username }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <code class="code rounded-lg bg-white px-3 py-2 text-xl tracking-wider text-stone-900 select-all dark:bg-stone-900 dark:text-white" x-ref="pw">{{ session('temporary_password') }}</code>
                <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard?.writeText($refs.pw.textContent.trim()); copied = true" x-text="copied ? 'Copiada' : 'Copiar'">Copiar</button>
            </div>
            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">
                Pasásela por un medio seguro (en persona o por mensaje directo). Se muestra sólo esta vez: el sistema le va a pedir
                que la cambie apenas ingrese, así que después ni vos la vas a conocer.
            </p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Datos personales y de contacto">
                <x-dl :items="[
                    'Nombre' => $user->full_name,
                    'Usuario para ingresar' => $user->username,
                    'Email' => $user->email,
                    'Teléfono' => $user->phone,
                    'DNI' => $user->dni,
                    'CUIT' => $user->cuit,
                    'Código interno' => $user->internal_code,
                    'Embalador vinculado' => $user->packer?->full_name,
                    'Galpones' => $user->warehouses->pluck('name')->join(', '),
                    'Observaciones' => $user->notes,
                ]"/>
            </x-panel>

            <x-panel title="Acceso y seguridad">
                <x-dl :items="[
                    'Estado' => $user->status->label(),
                    'Acceso' => $user->isSuperAdmin() || $user->role?->slug === 'admin' ? 'Acceso total'
                        : (($s = \App\Support\Sectors::of($user)) ? 'Sectores: '.collect($s)->map(fn ($k) => \App\Support\Sectors::all()[$k]['label'])->join(', ') : ($user->role?->name ?? 'Sin rol')),
                    'Contraseña' => 'Cifrada (no se puede ver)'.($user->must_change_password ? ' · temporal: debe cambiarla al ingresar' : ($user->password_changed_at ? ' · cambiada el '.fdate($user->password_changed_at, true) : '')),
                    'Último ingreso' => $user->last_login_at ? fdate($user->last_login_at, true).($user->last_login_ip ? ' · IP '.$user->last_login_ip : '') : 'Nunca ingresó',
                    'Sesiones abiertas' => $sessions === null ? null : num($sessions),
                    'Alta' => fdate($user->created_at, true),
                    'Fecha de baja' => fdate($user->deactivated_at, true),
                ]"/>
            </x-panel>

            <x-panel title="Actividad reciente">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    @forelse ($activity as $log)
                        <li class="flex flex-wrap justify-between gap-2 py-2">
                            <span>{{ $log->description ?: __('audit.actions.'.$log->action) }}</span>
                            <span class="text-xs text-stone-500 tabular-nums">{{ fdate($log->created_at, true) }}{{ $log->ip_address ? ' · IP '.$log->ip_address : '' }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">Sin actividad registrada.</li>
                    @endforelse
                </ul>
            </x-panel>
        </div>

        <div class="space-y-6">
            @unless ($user->is(auth()->user()))
                <x-panel title="Se olvidó la contraseña">
                    <p class="text-sm text-stone-600 dark:text-stone-400">Las contraseñas están cifradas: nadie puede verlas. Generale una temporal y el sistema le pedirá cambiarla al entrar.</p>
                    <form method="POST" action="{{ route('superadmin.users.password', $user) }}" class="mt-3" x-data x-confirm="¿Generar una contraseña temporal para {{ $user->full_name }}? Se cierran sus sesiones abiertas.">
                        @csrf
                        <button class="btn btn-primary w-full"><x-icon name="key" class="size-4"/> Generar contraseña temporal</button>
                    </form>
                </x-panel>
            @endunless

            <x-panel title="Se olvidó el usuario o el email">
                <form method="POST" action="{{ route('superadmin.users.access', $user) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <x-input name="username" label="Usuario para ingresar" :value="old('username', $user->username)" required autocomplete="off"/>
                    <x-input name="email" type="email" label="Email" :value="old('email', $user->email)"/>
                    <x-input name="phone" label="Teléfono" :value="old('phone', $user->phone)"/>
                    <x-input name="reason" label="Motivo" required minlength="5" maxlength="255" placeholder="Ej.: no recordaba su email"/>
                    <button class="btn btn-secondary w-full"><x-icon name="pencil" class="size-4"/> Guardar datos de acceso</button>
                </form>
            </x-panel>

            @unless ($user->is(auth()->user()))
                <x-panel title="Estado del usuario">
                    <form method="POST" action="{{ route('superadmin.users.status', $user) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <x-select name="status" label="Estado" :options="$statuses" :value="old('status', $user->status->value)"/>
                        <x-input name="reason" label="Motivo" required minlength="5" maxlength="255" placeholder="Ej.: dejó de trabajar en el galpón"/>
                        <button class="btn btn-secondary w-full">Guardar estado</button>
                    </form>
                    <form method="POST" action="{{ route('superadmin.users.sessions', $user) }}" class="mt-3" x-data x-confirm="¿Cerrar todas las sesiones de {{ $user->full_name }}? Tendrá que volver a ingresar.">
                        @csrf
                        <button class="btn btn-ghost w-full"><x-icon name="logout" class="size-4"/> Cerrar todas sus sesiones</button>
                    </form>
                    <p class="mt-2 text-xs text-stone-500">Útil si perdió el celular o dejó la sesión abierta en otra PC.</p>
                </x-panel>
            @endunless

            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost w-full"><x-icon name="cog" class="size-4"/> Editar rol y sectores</a>
        </div>
    </div>
</x-layouts.app>
