<x-layouts.app title="Detalle de auditoría">
    <x-page-header :title="__('audit.actions.'.$log->action)" :subtitle="fdate($log->created_at, true)" :back="route('admin.audit.index')"/>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Operación">
            <x-dl class="!grid-cols-1" :items="[
                'Usuario' => $log->user?->full_name ?? 'Sistema',
                'Entidad' => trim($log->auditable_type.' #'.$log->auditable_id, ' #'),
                'Descripción' => $log->description,
                'Motivo' => $log->reason,
                'IP' => $log->ip_address,
                'Navegador' => $log->user_agent,
                'URL' => $log->url,
            ]"/>
        </x-panel>

        <x-panel title="Cambios" class="lg:col-span-2" :padding="false">
            @php
                $old = $log->old_values ?? [];
                $new = $log->new_values ?? [];
                $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
            @endphp
            <table class="table">
                <thead><tr><th>Campo</th><th>Valor anterior</th><th>Valor nuevo</th></tr></thead>
                <tbody>
                    @forelse ($keys as $key)
                        <tr>
                            <td>{{ field_label($key) }} <span class="code text-xs text-stone-400">{{ $key }}</span></td>
                            <td class="text-red-700 dark:text-red-400">{{ is_array($old[$key] ?? null) ? json_encode($old[$key], JSON_UNESCAPED_UNICODE) : ($old[$key] ?? '—') }}</td>
                            <td class="text-emerald-700 dark:text-emerald-400">{{ is_array($new[$key] ?? null) ? json_encode($new[$key], JSON_UNESCAPED_UNICODE) : ($new[$key] ?? '—') }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="3" message="Esta operación no registró cambios de campos."/>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    </div>
</x-layouts.app>
