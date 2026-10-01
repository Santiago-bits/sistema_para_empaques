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

        <x-panel title="Acceso">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="username" label="Usuario" :value="$user->username" required autocomplete="off"/>
                <x-input name="internal_code" label="Código interno" :value="$user->internal_code"/>
                <x-select name="role_id" label="Rol" :options="$roles" :value="$user->role_id" placeholder="Seleccionar…" required/>
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
            <div class="mt-4">
                <x-checkbox name="kiosk_mode" label="Modo kiosco" :checked="$user->kiosk_mode"
                            hint="El usuario sólo verá la pantalla de escaneo (PCs dedicadas a producción)."/>
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
