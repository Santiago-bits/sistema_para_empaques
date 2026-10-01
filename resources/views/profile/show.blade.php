<x-layouts.app title="Mi perfil">
    <x-page-header title="Mi perfil" :subtitle="$user->role?->name"/>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-panel title="Datos">
            <x-dl :items="[
                'Nombre' => $user->full_name,
                'Usuario' => $user->username,
                'DNI' => $user->dni,
                'CUIT' => $user->cuit,
                'Código interno' => $user->internal_code,
                'Email' => $user->email,
                'Teléfono' => $user->phone,
                'Último acceso' => fdate($user->last_login_at, true),
            ]"/>
        </x-panel>

        <x-panel title="Cambiar contraseña">
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-input name="current_password" type="password" label="Contraseña actual" required autocomplete="current-password"/>
                <x-input name="password" type="password" label="Nueva contraseña" required autocomplete="new-password" hint="Mínimo 8 caracteres, con letras y números."/>
                <x-input name="password_confirmation" type="password" label="Repetir nueva contraseña" required autocomplete="new-password"/>
                <button class="btn btn-primary">Actualizar contraseña</button>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
