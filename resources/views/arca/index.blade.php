<x-layouts.app title="ARCA">
    <x-page-header title="Integración con ARCA" subtitle="Factura electrónica (WSFEv1). La configuración se cambia en Sistema → Configuración → ARCA.">
        <x-slot:actions>
            @can('settings.manage')
                <a href="{{ route('admin.settings.index', ['tab' => 'arca']) }}" class="btn btn-secondary"><x-icon name="cog" class="size-4"/> Configurar</a>
            @endcan
            <form method="POST" action="{{ route('arca.test') }}">
                @csrf
                <button class="btn btn-primary"><x-icon name="signal" class="size-4"/> Probar conexión</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Modo" :value="$mode->label()" icon="bank" :color="$mode->value === 'production' ? 'brand' : ($mode->value === 'homologation' ? 'amber' : 'sky')"/>
        <x-stat label="Punto de venta" :value="str_pad((string) $pointOfSale, 4, '0', STR_PAD_LEFT)" icon="receipt" color="violet"/>
        <x-stat label="CUIT emisor" :value="$cuit ? \App\Rules\Cuit::format($cuit) : 'Sin configurar'" icon="key" :color="$cuit ? 'stone' : 'red'"/>
        <x-stat label="Pendientes / rechazados" :value="num($pending)" icon="alert" :color="$pending ? 'amber' : 'stone'"/>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div @class(['panel p-4 text-sm', 'border-emerald-400' => $certificateOk])>
            <p class="font-semibold">Certificado (.crt)</p>
            <p class="{{ $certificateOk ? 'text-emerald-600' : 'text-stone-500' }}">{{ $certificateOk ? 'Configurado y legible' : 'No configurado (ARCA_CERT_PATH)' }}</p>
        </div>
        <div @class(['panel p-4 text-sm', 'border-emerald-400' => $keyOk])>
            <p class="font-semibold">Clave privada (.key)</p>
            <p class="{{ $keyOk ? 'text-emerald-600' : 'text-stone-500' }}">{{ $keyOk ? 'Configurada y legible' : 'No configurada (ARCA_KEY_PATH)' }}</p>
        </div>
        <div class="panel p-4 text-sm">
            <p class="font-semibold">Entorno del servidor</p>
            <p class="text-stone-500">{{ $environment }} · {{ $mode->value === 'production' && $environment !== 'production' ? 'Producción bloqueada fuera del entorno productivo' : 'OK' }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
        <p class="font-semibold">Pasos recomendados</p>
        <ol class="mt-1 list-inside list-decimal space-y-1">
            <li>Trabajar en <strong>simulación</strong> hasta validar precios e ítems (no se conecta a ARCA).</li>
            <li>Generar el certificado de <strong>homologación</strong> en ARCA, configurarlo en el .env y probar la conexión.</li>
            <li>Emitir comprobantes de prueba en homologación y verificar CAE.</li>
            <li>Con el certificado de producción y el servidor en entorno productivo, pasar a <strong>producción</strong>.</li>
        </ol>
    </div>

    <x-table>
        <thead><tr><th>Fecha</th><th>Comprobante</th><th>Operación</th><th>Modo</th><th>Resultado</th><th>Detalle</th><th>Usuario</th></tr></thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td class="tabular-nums">{{ fdate($record->created_at, true) }}</td>
                    <td>@if ($record->invoice)<a href="{{ route('invoices.show', $record->invoice) }}" class="link">{{ $record->invoice->voucherLabel() }} {{ $record->invoice->formattedNumber() }}</a>@endif</td>
                    <td class="code text-xs">{{ $record->operation }}</td>
                    <td>{{ \App\Enums\ArcaMode::tryFrom($record->mode)?->label() }}</td>
                    <td><x-badge :color="$record->status === 'success' ? 'emerald' : 'red'">{{ $record->status === 'success' ? 'Aprobado' : 'Error' }}</x-badge></td>
                    <td class="max-w-xs truncate text-xs text-stone-500" title="{{ $record->error_message }}">{{ $record->error_message }}</td>
                    <td>{{ $record->user?->full_name }}</td>
                </tr>
            @empty
                <x-empty colspan="7" message="Sin envíos registrados."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
