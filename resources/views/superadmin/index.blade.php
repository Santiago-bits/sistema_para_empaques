<x-layouts.app title="Administración general">
    <x-page-header title="Administración general" subtitle="Usuarios, clientes, pagos y soporte del sistema completo."/>

    @include('superadmin._nav')

    @if ($pendingMigrations !== [] || ($upgradeResult && ! $upgradeResult['ok']))
        <div class="panel mb-6 flex flex-wrap items-center justify-between gap-4 border-red-300 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/40" role="alert">
            <div class="max-w-2xl text-sm text-red-900 dark:text-red-200">
                <p class="font-semibold">La base de datos no está al día con la versión {{ config('galpon.version') }}</p>
                <p class="mt-1">Faltan {{ count($pendingMigrations) }} actualización(es). Mientras tanto, algunas pantallas nuevas pueden dar error.</p>
                @if ($upgradeResult && ! $upgradeResult['ok'])
                    <p class="mt-1">Último intento: {{ $upgradeResult['error'] ?? 'error desconocido' }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('superadmin.upgrade') }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button class="btn btn-primary" :disabled="busy"><x-icon name="refresh" class="size-4"/> <span x-text="busy ? 'Actualizando…' : 'Actualizar base de datos'">Actualizar base de datos</span></button>
            </form>
        </div>
    @endif

    <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Usuarios de este sistema</h2>
    <div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-stat label="Usuarios" :value="num($users['total'])" icon="users" :hint="num($users['active']).' activos'" :href="route('superadmin.users.index')"/>
        <x-stat label="Con contraseña temporal" :value="num($users['temporary'])" icon="key" :color="$users['temporary'] ? 'amber' : 'stone'" :href="route('superadmin.users.index', ['filter' => 'temporary'])"/>
        <x-stat label="Nunca ingresaron" :value="num($users['never'])" icon="clock" color="stone" :href="route('superadmin.users.index', ['filter' => 'never'])"/>
        <x-stat label="Accesos fallidos (24 h)" :value="num($users['failed_24h'])" icon="alert" :color="$users['failed_24h'] >= 10 ? 'red' : 'stone'"/>
        <x-stat label="Contraseñas" value="Cifradas" icon="lock" color="brand" hint="Nadie puede verlas, ni vos"/>
    </div>

    @if ($central)
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Clientes</h2>
        <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-stat label="Empaques clientes" :value="num($clients['total'])" icon="briefcase" :href="route('superadmin.clients.index')"/>
            <x-stat label="Conectados (24 h)" :value="num($clients['online'])" icon="signal" color="sky"/>
            <x-stat label="Al día" :value="num($clients['ok'])" icon="check" color="brand" :href="route('superadmin.clients.index', ['payment' => 'ok'])"/>
            <x-stat label="Por vencer" :value="num($clients['due_soon'])" icon="clock" :color="$clients['due_soon'] ? 'amber' : 'stone'" :href="route('superadmin.clients.index', ['payment' => 'due_soon'])"/>
            <x-stat label="Vencidos o sin pagar" :value="num($clients['overdue'])" icon="alert" :color="$clients['overdue'] ? 'red' : 'stone'" :href="route('superadmin.clients.index', ['payment' => 'overdue'])"/>
            <x-stat label="Cobrado este mes" :value="money($clients['month_income'])" icon="currency" color="accent"/>
        </div>

        <div class="mb-8 grid gap-6 xl:grid-cols-3">
            <x-panel title="Pagos para revisar">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    @forelse ($attention as $client)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('superadmin.clients.show', $client) }}" class="font-medium hover:underline">{{ $client->client_name }}</a>
                            <span class="text-right">
                                <x-badge :color="$client->paymentColor()">{{ $client->paymentLabel() }}</x-badge>
                                <span class="block text-xs text-stone-500">{{ $client->paid_until ? 'Pagado hasta '.fdate($client->paid_until) : 'Nunca pagó' }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">Todos los clientes con cuota están al día.</li>
                    @endforelse
                </ul>
            </x-panel>

            <x-panel title="Pedidos de soporte abiertos ({{ $openTickets }})">
                <x-slot:actions><a href="{{ route('central.tickets.index') }}" class="link text-sm">Ver todos</a></x-slot:actions>
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    @forelse ($tickets as $ticket)
                        <li class="py-2">
                            <a href="{{ route('central.tickets.show', $ticket) }}" class="font-medium hover:underline">{{ $ticket->subject }}</a>
                            <span class="block text-xs text-stone-500">{{ $ticket->license?->client_name }} · {{ $ticket->last_message_at?->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">No hay pedidos pendientes.</li>
                    @endforelse
                </ul>
            </x-panel>

            <x-panel title="Sin conexión reciente">
                <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                    @forelse ($offline as $client)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('superadmin.clients.show', $client) }}" class="font-medium hover:underline">{{ $client->client_name }}</a>
                            <span class="text-xs text-stone-500">{{ $client->last_seen_at ? $client->last_seen_at->diffForHumans() : 'Nunca se conectó' }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-stone-500">Todos los clientes usaron el sistema en las últimas 24 horas.</li>
                    @endforelse
                </ul>
            </x-panel>
        </div>
    @else
        <div class="panel mb-8 flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="max-w-2xl">
                <p class="font-semibold">Gestión de clientes</p>
                <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
                    Activala si desde este sistema vas a administrar a tus clientes: cargar cada empaque, registrar sus pagos,
                    ver si usan el sistema y recibir sus pedidos de soporte (con aviso en la campanita y por email).
                </p>
            </div>
            <form method="POST" action="{{ route('superadmin.central') }}" x-data x-confirm="¿Activar la gestión de clientes en este sistema?">
                @csrf @method('PUT')
                <input type="hidden" name="enable" value="1">
                <button class="btn btn-primary"><x-icon name="briefcase" class="size-4"/> Activar gestión de clientes</button>
            </form>
        </div>
    @endif

    @unless (setting('system.sample_data_at'))
        <div class="panel mb-8 flex flex-wrap items-center justify-between gap-4 border-sky-300 p-5 dark:border-sky-900">
            <div class="max-w-2xl">
                <p class="font-semibold">Datos de ejemplo</p>
                <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
                    Carga al menos 10 ejemplos de todo: productores, clientes, proveedores, camioneros, camiones, cámaras de frío, lotes,
                    pallets, cajones con etiqueta, cargas, remitos, facturas, caja, cheques, mantenimiento, incidentes y más. Sirve para ver
                    cómo se ve cada pantalla y probarla. Úsalo en un sistema de prueba, no en el de un cliente real.
                </p>
            </div>
            <form method="POST" action="{{ route('superadmin.sample') }}" x-data="{ busy: false }" x-confirm="¿Cargar los datos de ejemplo? Tarda alrededor de un minuto." @submit="busy = true">
                @csrf
                <button class="btn btn-primary" :disabled="busy"><x-icon name="download" class="size-4"/> <span x-text="busy ? 'Cargando… (puede tardar un minuto)' : 'Cargar datos de ejemplo'">Cargar datos de ejemplo</span></button>
            </form>
        </div>
    @endunless

    <x-panel title="Últimos accesos fallidos">
        <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
            @forelse ($failedLogins as $log)
                <li class="flex flex-wrap justify-between gap-2 py-2">
                    <span>{{ $log->new_values['login'] ?? 'Usuario desconocido' }}</span>
                    <span class="text-xs text-stone-500 tabular-nums">{{ fdate($log->created_at, true) }} · IP {{ $log->ip_address }}</span>
                </li>
            @empty
                <li class="py-2 text-stone-500">Sin intentos fallidos.</li>
            @endforelse
        </ul>
    </x-panel>
</x-layouts.app>
