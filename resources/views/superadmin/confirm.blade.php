<x-layouts.app title="Administración general">
    <div class="mx-auto mt-10 max-w-md">
        <div class="panel p-6">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-full bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-300"><x-icon name="shield" class="size-5"/></span>
                <div>
                    <h1 class="text-lg font-semibold">Administración general</h1>
                    <p class="text-sm text-stone-500">Zona con datos personales de todos los usuarios.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('superadmin.confirm.store') }}" class="mt-6 space-y-4">
                @csrf
                <x-input name="password" type="password" label="Volvé a escribir tu contraseña" required autofocus autocomplete="current-password"
                         :hint="'Se pide cada '.$minutes.' minutos, así nadie entra aunque encuentre tu sesión abierta.'"/>
                <button class="btn btn-primary w-full"><x-icon name="lock" class="size-4"/> Entrar</button>
            </form>
        </div>
        <p class="mt-4 text-center text-xs text-stone-500">Cada ingreso y cada consulta de datos queda registrada en la auditoría.</p>
    </div>
</x-layouts.app>
