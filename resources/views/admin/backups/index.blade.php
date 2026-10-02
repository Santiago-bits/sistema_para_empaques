@php
    $statusColors = ['success' => 'emerald', 'failed' => 'red', 'running' => 'sky', 'pruned' => 'stone'];
    $restoreWord = \App\Http\Controllers\Admin\BackupController::RESTORE_WORD;
@endphp
<x-layouts.app title="Backups">
    <x-page-header title="Backups" subtitle="Copias de seguridad de la base de datos, comprimidas y verificadas con checksum SHA-256.">
        <x-slot:actions>
            <form method="POST" action="{{ route('backups.store') }}" x-data x-confirm="¿Generar un backup ahora? Puede tardar unos minutos.">
                @csrf
                <button class="btn btn-primary"><x-icon name="database" class="size-4"/> Generar backup ahora</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    @foreach ($health['warnings'] as $warning)
        <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            <x-icon name="alert" class="mr-1 inline size-4"/> {{ $warning }}
        </div>
    @endforeach

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-stat label="Último backup correcto" :value="$health['last'] ? fdate($health['last']->finished_at, true) : 'Nunca'" icon="database" :color="$health['last'] ? 'brand' : 'red'"/>
        <x-stat label="Tamaño" :value="$health['last'] ? $service->humanSize($health['last']->size) : '—'" icon="archive" color="stone"/>
        <x-stat label="Automático diario" :value="setting('backup.daily', true) ? '02:00 hs' : 'Apagado'" icon="clock" color="sky"/>
        <x-stat label="Retención" :value="setting('backup.retention_days', 30).' días'" icon="trash" color="stone"/>
    </div>

    <x-table>
        <thead><tr><th>Archivo</th><th>Tipo</th><th>Estado</th><th class="num">Tamaño</th><th>Fecha</th><th>Verificado</th><th>Por</th><th></th></tr></thead>
        <tbody>
            @forelse ($backups as $backup)
                <tr>
                    <td class="code text-xs">{{ $backup->filename }}</td>
                    <td>{{ \App\Services\BackupService::typeLabel($backup->type) }}</td>
                    <td>
                        <x-badge :color="$statusColors[$backup->status] ?? 'stone'">{{ \App\Services\BackupService::STATUSES[$backup->status] ?? $backup->status }}</x-badge>
                        @if ($backup->error)<p class="mt-1 max-w-xs text-xs text-red-600 dark:text-red-400">{{ \Illuminate\Support\Str::limit($backup->error, 140) }}</p>@endif
                    </td>
                    <td class="num">{{ $backup->size ? $service->humanSize($backup->size) : '—' }}</td>
                    <td>{{ fdate($backup->finished_at ?? $backup->started_at, true) }}</td>
                    <td>{{ $backup->verified_at ? fdate($backup->verified_at, true) : '—' }}</td>
                    <td class="text-stone-500">{{ $backup->creator?->full_name ?? 'Automático' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($backup->status === 'success')
                            <a href="{{ route('backups.download', $backup) }}" class="link">Descargar</a>
                            <form method="POST" action="{{ route('backups.verify', $backup) }}" class="inline">
                                @csrf
                                <button class="link ml-3">Verificar</button>
                            </form>
                            @can('backups.restore')
                                <button type="button" class="ml-3 text-sm font-medium text-red-600 hover:underline dark:text-red-400" @click="$dispatch('open-modal', 'restore-{{ $backup->id }}')">Restaurar</button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="8" message="Todavía no se generó ningún backup."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $backups->links() }}</x-slot:footer>
    </x-table>

    @can('backups.restore')
        @foreach ($backups as $backup)
            @if ($backup->status === 'success')
                <x-modal :name="'restore-'.$backup->id" title="Restaurar backup">
                    <form method="POST" action="{{ route('backups.restore', $backup) }}" class="space-y-4">
                        @csrf
                        <div class="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">
                            <p class="font-semibold">Esto reemplaza TODOS los datos actuales por los del {{ fdate($backup->finished_at, true) }}.</p>
                            <p class="mt-1">Antes se guarda automáticamente un backup del estado actual. Mientras dura, el sistema queda en mantenimiento para todos los usuarios.</p>
                        </div>
                        <x-input name="reason" label="Motivo" required minlength="5" maxlength="255"/>
                        <x-input name="password" type="password" label="Tu contraseña" required autocomplete="current-password"/>
                        <x-input name="confirmation" :label="'Escribí '.$restoreWord.' para confirmar'" required autocomplete="off" :pattern="$restoreWord"/>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-secondary" @click="$dispatch('close-modal', 'restore-{{ $backup->id }}')">Cancelar</button>
                            <button class="btn btn-danger">Restaurar</button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endcan

    @if ($errors->hasAny(['confirmation', 'password', 'reason']))
        <p class="mt-4 text-sm text-red-600 dark:text-red-400" role="alert">{{ $errors->first('confirmation') ?: $errors->first('password') ?: $errors->first('reason') }}</p>
    @endif
</x-layouts.app>
