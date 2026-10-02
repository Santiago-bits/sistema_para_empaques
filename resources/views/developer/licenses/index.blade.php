@php
    $statuses = \App\Http\Controllers\Developer\LicenseController::STATUSES;
    $plans = \App\Http\Controllers\Developer\LicenseController::PLANS;
    $colors = ['active' => 'emerald', 'suspended' => 'amber', 'expired' => 'red'];
@endphp
<x-layouts.app title="Licencias">
    <x-page-header title="Panel desarrollador" subtitle="Licencias por instalación. Una licencia vencida sólo muestra un aviso: nunca bloquea datos.">
        <x-slot:actions>
            <a href="{{ route('developer.licenses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4"/> Nueva licencia</a>
        </x-slot:actions>
    </x-page-header>
    @include('developer._nav')

    <x-table>
        <thead><tr><th>Instalación</th><th>Cliente</th><th>Plan</th><th>Estado</th><th>Vigencia</th><th>Clave</th><th></th></tr></thead>
        <tbody>
            @forelse ($licenses as $license)
                @php $status = $license->effectiveStatus(); @endphp
                <tr>
                    <td class="code">{{ $license->installation_id }} @if ($license->installation_id === $installationId)<x-badge color="sky">Esta</x-badge>@endif</td>
                    <td class="font-medium text-stone-900 dark:text-white">{{ $license->client_name }}</td>
                    <td>{{ $plans[$license->plan] ?? $license->plan }}</td>
                    <td><x-badge :color="$colors[$status] ?? 'stone'">{{ $statuses[$status] ?? $status }}</x-badge></td>
                    <td class="whitespace-nowrap">{{ fdate($license->starts_on) }} → {{ $license->expires_on ? fdate($license->expires_on) : 'sin vencimiento' }}</td>
                    <td class="code text-xs">{{ $license->license_key }}</td>
                    <td class="text-right"><a href="{{ route('developer.licenses.edit', $license) }}" class="link">Editar</a></td>
                </tr>
            @empty
                <x-empty colspan="7" message="No hay licencias cargadas."/>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
