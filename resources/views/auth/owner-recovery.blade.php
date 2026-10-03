<x-auth-shell title="Recuperar acceso del dueño">
    <form method="POST" action="{{ route('owner.recovery.store') }}" class="space-y-5">
        @csrf
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Recuperar acceso del dueño</h1>
            <p class="mt-1 text-sm text-stone-500">
                Para quien administra el servidor y se olvidó el usuario o la contraseña del Super Administrador.
                Los empleados usan «¿Olvidaste tu contraseña?» o le piden una temporal al administrador.
            </p>
        </div>

        <x-input name="db_password" type="password" label="Contraseña de la base de datos" autocomplete="off"
                 hint="La misma que figura en hPanel → Bases de datos (o en la línea DB_PASSWORD= del archivo .env del servidor)."/>
        <x-input name="username" label="Usuario nuevo (opcional)" autocomplete="off" hint="Si lo dejás vacío se mantiene el usuario actual. Sin espacios: letras, números, guiones."/>
        <x-input name="password" type="password" label="Contraseña nueva del Super Administrador" required autocomplete="new-password" hint="Mínimo 8 caracteres, letras y números."/>
        <x-input name="password_confirmation" type="password" label="Repetir contraseña nueva" required autocomplete="new-password"/>

        <button type="submit" class="btn btn-primary w-full py-3 text-base">Recuperar acceso</button>
        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver al ingreso</a></p>
    </form>
</x-auth-shell>
