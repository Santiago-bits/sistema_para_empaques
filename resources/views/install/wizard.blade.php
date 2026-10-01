@php
    $steps = ['Datos del galpón', 'Administrador', 'Base de datos', 'Configuración regional', 'Módulos', 'Usuarios', 'Configuración inicial', 'Finalización'];
@endphp
<x-layouts.guest title="Instalación">
    <div class="mx-auto max-w-4xl px-4 py-10" x-data="{ step: {{ $errors->any() ? 0 : 0 }}, total: {{ count($steps) }}, users: [] }">
        <div class="mb-8">
            <p class="font-mono text-xs tracking-widest text-brand-600 uppercase">Asistente de instalación</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Configuración inicial del sistema</h1>
        </div>

        <ol class="mb-8 grid grid-cols-4 gap-2 md:grid-cols-8">
            @foreach ($steps as $i => $label)
                <li>
                    <button type="button" @click="step = {{ $i }}" class="w-full text-left">
                        <span class="block h-1.5 rounded-full" :class="step >= {{ $i }} ? 'bg-brand-600' : 'bg-stone-300 dark:bg-stone-700'"></span>
                        <span class="mt-1.5 block text-[11px] leading-tight" :class="step === {{ $i }} ? 'font-semibold text-stone-900 dark:text-white' : 'text-stone-500'">{{ $i + 1 }}. {{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        <x-flash/>

        <form method="POST" action="{{ route('install.store') }}" class="panel p-6">
            @csrf

            <section x-show="step === 0" class="grid gap-4 md:grid-cols-2">
                <h2 class="text-lg font-semibold md:col-span-2">Datos del galpón</h2>
                <x-input name="company_name" label="Nombre de la empresa / galpón" required/>
                <x-input name="warehouse_name" label="Nombre del galpón principal" value="Galpón A" required/>
                <x-input name="company_cuit" label="CUIT" inputmode="numeric" hint="11 dígitos, sin guiones"/>
                <x-input name="company_phone" label="Teléfono"/>
                <x-input name="company_address" label="Dirección" class="md:col-span-2"/>
            </section>

            <section x-show="step === 1" x-cloak class="grid gap-4 md:grid-cols-2">
                <h2 class="text-lg font-semibold md:col-span-2">Usuario Super Administrador</h2>
                <x-input name="admin_first_name" label="Nombre" required/>
                <x-input name="admin_last_name" label="Apellido" required/>
                <x-input name="admin_username" label="Usuario" required autocomplete="off"/>
                <x-input name="admin_dni" label="DNI" inputmode="numeric"/>
                <x-input name="admin_email" type="email" label="Email (opcional)"/>
                <div></div>
                <x-input name="admin_password" type="password" label="Contraseña" required autocomplete="new-password" hint="Mínimo 8 caracteres, letras y números."/>
                <x-input name="admin_password_confirmation" type="password" label="Repetir contraseña" required autocomplete="new-password"/>
            </section>

            <section x-show="step === 2" x-cloak>
                <h2 class="text-lg font-semibold">Base de datos</h2>
                <p class="mt-1 text-sm text-stone-500">La conexión se configura en el archivo <span class="code">.env</span> del servidor.</p>
                <div @class(['mt-4 rounded-lg p-4 text-sm', 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' => $dbOk, 'bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200' => ! $dbOk])>
                    <p class="font-semibold">{{ $dbOk ? 'Conexión correcta' : 'Problema de conexión' }}</p>
                    <p class="mt-1">Motor: <span class="code">{{ $db['driver'] }}</span> · Servidor: <span class="code">{{ $db['host'] ?? 'local' }}</span> · Base: <span class="code">{{ $db['database'] }}</span></p>
                    @if ($dbError)<p class="mt-2">{{ $dbError }}</p>@endif
                </div>
            </section>

            <section x-show="step === 3" x-cloak class="grid gap-4 md:grid-cols-2">
                <h2 class="text-lg font-semibold md:col-span-2">Configuración regional</h2>
                <x-select name="currency" label="Moneda" :options="['ARS' => 'Peso argentino (ARS)', 'USD' => 'Dólar (USD)']" value="ARS"/>
                <x-input name="tz" label="Zona horaria" :value="config('app.timezone')" disabled/>
            </section>

            <section x-show="step === 4" x-cloak>
                <h2 class="text-lg font-semibold">Módulos</h2>
                <p class="mt-1 mb-4 text-sm text-stone-500">Podés cambiar esto luego en Sistema → Módulos sin perder datos.</p>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($modules as $key => [$name, $description, $default])
                        <label class="flex items-start gap-3 rounded-lg border border-stone-200 p-3 dark:border-stone-800">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', $default ? [$key] : []), true))
                                   class="mt-0.5 size-4 rounded border-stone-300 text-brand-600">
                            <span><span class="block text-sm font-medium">{{ $name }}</span><span class="text-xs text-stone-500">{{ $description }}</span></span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section x-show="step === 5" x-cloak>
                <h2 class="text-lg font-semibold">Usuarios adicionales (opcional)</h2>
                <p class="mt-1 mb-4 text-sm text-stone-500">También podés crearlos más tarde desde Sistema → Usuarios.</p>
                <template x-for="(u, i) in users" :key="i">
                    <div class="mb-3 grid gap-2 md:grid-cols-5">
                        <input class="form-input" :name="`users[${i}][first_name]`" placeholder="Nombre">
                        <input class="form-input" :name="`users[${i}][last_name]`" placeholder="Apellido">
                        <input class="form-input" :name="`users[${i}][username]`" placeholder="Usuario">
                        <select class="form-input" :name="`users[${i}][role]`">
                            @foreach ($roles as $slug => $role)
                                @continue($slug === 'super_admin')
                                <option value="{{ $slug }}">{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                        <input class="form-input" type="password" :name="`users[${i}][password]`" placeholder="Contraseña" autocomplete="new-password">
                    </div>
                </template>
                <button type="button" class="btn btn-secondary btn-sm" @click="users.push({})"><x-icon name="plus" class="size-4"/> Agregar usuario</button>
            </section>

            <section x-show="step === 6" x-cloak class="grid gap-4 md:grid-cols-3">
                <h2 class="text-lg font-semibold md:col-span-3">Parámetros de producción</h2>
                <x-input name="weight_min" type="number" step="0.01" label="Peso mínimo por cajón (kg)" value="5" required/>
                <x-input name="weight_max" type="number" step="0.01" label="Peso máximo por cajón (kg)" value="30" required/>
                <x-input name="target_daily_kg" type="number" label="Objetivo diario (kg)" value="10000" required/>
            </section>

            <section x-show="step === 7" x-cloak>
                <h2 class="text-lg font-semibold">Finalización</h2>
                <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
                    Al confirmar se crearán los roles, permisos, módulos, numeraciones y el usuario administrador.
                    Luego ingresarás automáticamente al sistema.
                </p>
                <button type="submit" class="btn btn-primary btn-lg mt-6" @disabled(! $dbOk)>Instalar sistema</button>
            </section>

            <div class="mt-8 flex justify-between border-t border-stone-200 pt-4 dark:border-stone-800">
                <button type="button" class="btn btn-secondary" @click="step = Math.max(0, step - 1)" x-show="step > 0">Anterior</button>
                <span x-show="step === 0"></span>
                <button type="button" class="btn btn-primary" @click="step = Math.min(total - 1, step + 1)" x-show="step < total - 1">Siguiente</button>
            </div>
        </form>
    </div>
</x-layouts.guest>
