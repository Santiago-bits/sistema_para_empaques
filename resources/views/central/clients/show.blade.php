@php
    $r = $license->latestReport;
    $statuses = \App\Http\Controllers\Developer\LicenseController::STATUSES;
    $status = $license->effectiveStatus();
    $chart = ['labels' => $daily->keys()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m'))->values(), 'crates' => $daily->map(fn ($x) => (int) $x->metric('crates_today'))->values(), 'users' => $daily->map(fn ($x) => (int) $x->metric('logins_today'))->values()];
    $envLines = "GALPON_INSTALLATION_ID={$license->installation_id}\nGALPON_CENTRAL_URL={$centralUrl}\nGALPON_LICENSE_KEY={$license->license_key}";
@endphp
<x-layouts.app :title="$license->client_name">
    <x-page-header :title="$license->client_name" :subtitle="trim(($license->locality ?? '').' · '.($license->contact_name ?? '').' '.($license->contact_phone ?? ''), ' ·')"
                   :back="route('central.clients.index')">
        <x-slot:actions>
            <x-badge :color="['active' => 'emerald', 'suspended' => 'red', 'expired' => 'amber'][$status] ?? 'stone'" class="text-sm">{{ $statuses[$status] ?? $status }}</x-badge>
            <a href="{{ route('central.clients.edit', $license) }}" class="btn btn-primary"><x-icon name="pencil" class="size-4"/> Editar</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat label="Última conexión" :value="$license->last_seen_at ? $license->last_seen_at->diffForHumans() : 'Nunca'" icon="signal" :color="$license->isOnline() ? 'brand' : 'amber'" :hint="$license->version ? 'Versión '.$license->version : null"/>
        <x-stat label="Usuarios activos (7 d)" :value="$r ? num($r->metric('users_active_7d')).' de '.num($r->metric('users_total')) : '—'" icon="users" color="violet"/>
        <x-stat label="Cajones (30 d)" :value="$r ? num($r->metric('crates_30d')) : '—'" icon="box" color="accent" :hint="$r ? kg($r->metric('kg_30d'), 0) : null"/>
        <x-stat label="Cargas / facturas (30 d)" :value="$r ? num($r->metric('loads_30d')).' / '.num($r->metric('invoices_30d')) : '—'" icon="truck" color="sky"/>
        <x-stat label="Errores (7 d)" :value="$r ? num($r->metric('errors_7d')) : '—'" icon="alert" :color="$r && $r->metric('errors_7d') > 0 ? 'red' : 'stone'"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-panel title="Uso de los últimos 30 días">
                @if ($daily->isEmpty())
                    <p class="text-sm text-stone-500">Todavía no llegaron reportes de este empaque. Se envían solos cada hora cuando tiene internet.</p>
                @else
                    <div class="h-64" x-data x-init="new Chart($refs.c, { type: 'bar', data: { labels: {{ \Illuminate\Support\Js::from($chart['labels']) }}, datasets: [
                        { label: 'Cajones del día', data: {{ \Illuminate\Support\Js::from($chart['crates']) }}, backgroundColor: window.chartColors[0], borderRadius: 4 },
                        { label: 'Usuarios que entraron', data: {{ \Illuminate\Support\Js::from($chart['users']) }}, type: 'line', borderColor: window.chartColors[2], yAxisID: 'y1', tension: .3 } ] },
                        options: { scales: { y: { beginAtZero: true }, y1: { beginAtZero: true, position: 'right', grid: { display: false } } } } })">
                        <canvas x-ref="c"></canvas>
                    </div>
                @endif
            </x-panel>

            <x-panel title="Pedidos de soporte" :padding="false">
                <table class="table">
                    <thead><tr><th>N°</th><th>Asunto</th><th>Estado</th><th>Último mensaje</th></tr></thead>
                    <tbody>
                        @forelse ($tickets as $t)
                            <tr>
                                <td class="code">{{ $t->remote_number }}</td>
                                <td><a href="{{ route('central.tickets.show', $t) }}" class="link">{{ $t->subject }}</a></td>
                                <td><x-badge :color="\App\Services\SupportService::STATUS_COLORS[$t->status] ?? 'stone'">{{ \App\Models\SupportTicket::STATUSES[$t->status] ?? $t->status }}</x-badge></td>
                                <td class="text-stone-500">{{ fdate($t->last_message_at, true) }}</td>
                            </tr>
                        @empty
                            <x-empty colspan="4" message="Sin pedidos de soporte."/>
                        @endforelse
                    </tbody>
                </table>
            </x-panel>
        </div>

        <div class="space-y-6">
            <x-panel title="Datos de conexión del empaque">
                <p class="mb-3 text-sm text-stone-600 dark:text-stone-300">Pegá estas líneas en el archivo <span class="code">.env</span> del servidor del empaque y ejecutá <span class="code">php artisan config:cache</span>.</p>
                <div x-data="{ copied: false }">
                    <pre class="overflow-x-auto rounded-lg bg-stone-900 p-3 text-xs text-emerald-300">{{ $envLines }}</pre>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" @click="navigator.clipboard?.writeText({{ \Illuminate\Support\Js::from($envLines) }}); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copiado' : 'Copiar'">Copiar</span>
                    </button>
                </div>
                <p class="form-hint mt-3">La clave es secreta: no la compartas por canales públicos. Este servidor debe ser accesible por internet (idealmente con https).</p>
            </x-panel>

            <x-panel title="Licencia">
                <x-dl :items="[
                    'Plan' => \App\Http\Controllers\Developer\LicenseController::PLANS[$license->plan] ?? $license->plan,
                    'Inicio' => fdate($license->starts_on),
                    'Vencimiento' => $license->expires_on ? fdate($license->expires_on) : 'Sin vencimiento',
                    'Módulos' => collect($license->modules ?? [])->map(fn ($m) => \App\Services\ModuleService::CATALOG[$m][0] ?? $m)->join(', ') ?: null,
                    'Contacto' => trim(($license->contact_name ?? '').' '.($license->contact_email ?? '')) ?: null,
                    'Notas' => $license->notes,
                ]"/>
            </x-panel>

            @if ($r)
                <x-panel title="Último reporte">
                    <x-dl :items="[
                        'Recibido' => fdate($r->reported_at, true),
                        'Última actividad' => $r->metric('last_activity_at', null) ? fdate(\Illuminate\Support\Carbon::parse($r->metric('last_activity_at')), true) : null,
                        'Módulos activos' => collect($r->metric('modules', []))->map(fn ($m) => \App\Services\ModuleService::CATALOG[$m][0] ?? $m)->join(', ') ?: null,
                        'Tickets abiertos' => num($r->metric('open_tickets')),
                        'Servidor' => 'PHP '.$r->metric('php', '?').' · '.$r->metric('db', '?'),
                    ]"/>
                </x-panel>
            @endif
        </div>
    </div>
</x-layouts.app>
