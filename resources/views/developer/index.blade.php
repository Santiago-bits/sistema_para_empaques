@php
    $healthColor = ['ok' => 'emerald', 'warning' => 'amber', 'error' => 'red'][$health['status']];
    $checkLabels = ['database' => 'Base de datos', 'backup' => 'Backups al día', 'storage_writable' => 'Carpeta storage escribible'];
@endphp
<x-layouts.app title="Panel desarrollador">
    <x-page-header title="Panel desarrollador" :subtitle="'Versión '.$version.' · '.config('galpon.installation_id')">
        <x-slot:actions><x-badge :color="$healthColor">{{ $health['label'] }}</x-badge></x-slot:actions>
    </x-page-header>
    @include('developer._nav')

    @if ($schedulerStale)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            <strong>El programador de tareas no está corriendo</strong> {{ $scheduler ? '(última vez '.$scheduler->diffForHumans().')' : '' }}.
            Sin él no hay backups automáticos, alertas ni cola de trabajos. Configurá la tarea de Windows con <span class="code">php artisan schedule:run</span> cada minuto (ver docs/INSTALACION.md).
        </div>
    @endif
    @if ($devServer)
        <div class="mb-4 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
            Corriendo con el servidor de desarrollo (<span class="code">php artisan serve</span>): atiende un pedido a la vez y es más lento. En el galpón usá Apache (docs/INSTALACION.md).
        </div>
    @elseif ($opcacheOff)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            <strong>OPcache está desactivado.</strong> Cada pedido recompila el sistema (~0,6 s de más). Activalo en <span class="code">php.ini</span> (docs/INSTALACION.md, paso 1).
        </div>
    @endif
    @if ($cachesOff && app()->environment('production'))
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            Faltan las cachés de configuración y rutas: ejecutá <span class="code">php artisan optimize</span>.
        </div>
    @endif
    @if ($missing)
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert">
            Faltan extensiones de PHP: <span class="code">{{ implode(', ', $missing) }}</span>
        </div>
    @endif
    @foreach ($backup['warnings'] as $warning)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ $warning }}</div>
    @endforeach

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Errores hoy" :value="num($errorsToday)" icon="alert" :color="$errorsToday ? 'red' : 'stone'" :href="route('developer.errors')"/>
        <x-stat label="Cola pendiente / fallida" :value="($queue['pending'] ?? '—').' / '.($queue['failed'] ?? '—')" icon="layers" :color="($queue['failed'] ?? 0) > 0 ? 'red' : 'sky'"/>
        <x-stat label="Disco usado" :value="$disk['used_pct'] !== null ? pct($disk['used_pct']) : '—'" icon="database" :color="($disk['used_pct'] ?? 0) > 90 ? 'red' : 'stone'" :hint="$disk['free'] !== null ? $backupService->humanSize($disk['free']).' libres' : null"/>
        <x-stat label="Último backup" :value="$backup['last'] ? $backup['last']->finished_at->diffForHumans() : 'Nunca'" icon="archive" :color="$backup['last'] ? 'brand' : 'red'" :href="route('backups.index')"/>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-panel title="Servidor">
            <x-dl :items="$server"/>
        </x-panel>
        <x-panel title="Configuración">
            <x-dl :items="$environment + ['Última migración' => $lastUpdate ? $lastUpdate['migration'] : null, 'Programador' => $scheduler ? fdate($scheduler, true) : 'Nunca corrió']"/>
        </x-panel>
        <x-panel title="Chequeos">
            <ul class="space-y-2 text-sm">
                @foreach ($health['checks'] as $key => $ok)
                    <li class="flex items-center gap-2">
                        <x-icon :name="$ok ? 'check' : 'x'" @class(['size-4', 'text-emerald-600' => $ok, 'text-red-600' => ! $ok])/>
                        {{ $checkLabels[$key] ?? $key }}
                    </li>
                @endforeach
            </ul>
            <h3 class="mt-5 mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">PHP</h3>
            <x-dl :items="$php"/>
        </x-panel>
        <x-panel title="Módulos">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($modules as $module)
                    <x-badge :color="$module['enabled'] ? 'emerald' : 'stone'">{{ $module['name'] }}</x-badge>
                @endforeach
            </div>
        </x-panel>
        <x-panel title="Últimos errores" :padding="false">
            <table class="table">
                <tbody>
                    @forelse ($recentErrors as $e)
                        <tr>
                            <td><a href="{{ route('developer.errors.show', $e) }}" class="link code">{{ $e->code }}</a></td>
                            <td class="max-w-xs truncate text-xs">{{ $e->message }}</td>
                            <td class="text-xs whitespace-nowrap text-stone-500">{{ $e->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="3" message="Sin errores registrados."/>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
        <x-panel title="Tablas más grandes" :padding="false">
            <table class="table">
                <thead><tr><th>Tabla</th><th class="num">Filas</th><th class="num">Tamaño</th></tr></thead>
                <tbody>
                    @foreach ($tables as $t)
                        <tr><td class="code">{{ $t['table'] }}</td><td class="num">{{ num($t['rows']) }}</td><td class="num">{{ $t['bytes'] !== null ? $backupService->humanSize($t['bytes']) : '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-panel>
    </div>
</x-layouts.app>
