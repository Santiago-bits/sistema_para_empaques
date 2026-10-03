<x-layouts.app :title="$user->exists ? 'Editar usuario' : 'Nuevo usuario'">
    <x-page-header :title="$user->exists ? 'Editar: '.$user->full_name : 'Nuevo usuario'"
                   :back="$user->exists ? route('admin.users.show', $user) : route('admin.users.index')"/>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-6">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <x-panel title="Datos personales">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="first_name" label="Nombre" :value="$user->first_name" required/>
                <x-input name="last_name" label="Apellido" :value="$user->last_name" required/>
                <x-input name="dni" label="DNI" :value="$user->dni" inputmode="numeric"/>
                <x-input name="cuit" label="CUIT" :value="$user->cuit" inputmode="numeric" hint="11 dígitos, sin guiones"/>
                <x-input name="phone" label="Teléfono" :value="$user->phone"/>
                <x-input name="email" type="email" label="Email (opcional)" :value="$user->email"/>
            </div>
        </x-panel>

        <x-panel title="¿A qué partes del sistema puede entrar?">
            <div x-data="{ mode: {{ \Illuminate\Support\Js::from($accessMode) }} }" class="space-y-4">
                <div class="grid gap-2 md:grid-cols-3" role="radiogroup" aria-label="Tipo de acceso">
                    @foreach (['sectors' => ['Por sectores', 'Tildá los sectores que atiende (recomendado).'], 'full' => ['Acceso total', 'Puede usar y configurar todo el sistema.'], 'role' => ['Rol predefinido', 'Elegí un rol armado (avanzado).']] as $value => [$title, $hint])
                        <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm" :class="mode === '{{ $value }}' ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40' : 'border-stone-200 dark:border-stone-700'">
                            <input type="radio" name="access_mode" value="{{ $value }}" x-model="mode" class="sr-only">
                            <span class="block font-semibold">{{ $title }}</span><span class="text-xs text-stone-500">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>
                @error('access_mode')<p class="form-error">{{ $message }}</p>@enderror

                <div x-show="mode === 'sectors'" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($sectors as $key => $sector)
                        @php $can = in_array($key, $assignableSectors, true); @endphp
                        <label @class(['flex gap-3 rounded-lg border border-stone-200 p-3 dark:border-stone-700', 'cursor-pointer hover:border-brand-400' => $can, 'opacity-50' => ! $can])>
                            <input type="checkbox" name="sectors[]" value="{{ $key }}" class="mt-1" @checked(in_array($key, (array) $currentSectors, true)) @disabled(! $can)>
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-stone-900 dark:text-white"><x-icon :name="$sector['icon']" class="size-4 text-brand-600"/> {{ $sector['label'] }}</span>
                                <span class="block text-xs text-stone-500">{{ $sector['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('sectors')<p class="form-error sm:col-span-2 xl:col-span-3">{{ $message }}</p>@enderror
                    <p class="form-hint sm:col-span-2 xl:col-span-3">Siempre ve el tablero, las alertas y puede pedir soporte. Para ajustar permisos sueltos, después usá «Permisos» en su ficha.</p>
                </div>

                <div x-show="mode === 'full'" x-cloak class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Tendrá el rol <strong>Administrador del galpón</strong>: ve y configura todo, incluidos usuarios y permisos.
                </div>

                <div x-show="mode === 'role'" x-cloak class="max-w-sm">
                    <x-select name="role_id" label="Rol" :options="$roles" :value="$user->role_id" placeholder="Seleccionar…" x-bind:disabled="mode !== 'role'"/>
                </div>
            </div>
        </x-panel>

        <x-panel title="Ingreso al sistema">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="username" label="Usuario" :value="$user->username" required autocomplete="off"/>
                <x-input name="internal_code" label="Código interno" :value="$user->internal_code"/>
                <x-select name="status" label="Estado" :options="$statuses" :value="$user->status" required/>
                <x-input name="password" type="password" label="Contraseña" :required="! $user->exists" autocomplete="new-password"
                         :hint="$user->exists ? 'Dejar vacío para no cambiarla.' : 'Mínimo 8 caracteres, letras y números.'"/>
                <x-input name="password_confirmation" type="password" label="Repetir contraseña" autocomplete="new-password"/>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <x-select name="packer_id" label="Embalador vinculado" :options="$packers" :value="$user->packer_id" placeholder="Ninguno"
                          hint="Para que el embalador consulte su propia producción."/>
                @if ($owners->isNotEmpty())
                    <x-select name="owner_id" label="Propietario (portal)" :options="$owners" :value="$user->owner_id" placeholder="Ninguno"/>
                @endif
                @if ($clients->isNotEmpty())
                    <x-select name="client_id" label="Cliente (portal)" :options="$clients" :value="$user->client_id" placeholder="Ninguno"/>
                @endif
                <x-select name="warehouses[]" label="Galpones con acceso" :options="$warehouses" multiple
                          :value="$user->exists ? $user->warehouses->pluck('id')->all() : array_keys($warehouses->all())"/>
            </div>
        </x-panel>

        <x-panel title="Observaciones">
            <x-textarea name="notes" :value="$user->notes"/>
        </x-panel>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
