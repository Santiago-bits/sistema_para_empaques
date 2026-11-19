<x-layouts.app title="Términos y condiciones">
    <x-page-header title="Administración general" subtitle="Usuarios, galpones, pagos y soporte del sistema completo."/>

    @include('superadmin._nav')

    <div class="grid gap-6 lg:grid-cols-2">
        <x-panel title="Titular del servicio">
            <p class="mb-4 text-sm text-stone-600 dark:text-stone-400">
                Aparecen en los <a href="{{ route('legal.terms') }}" target="_blank" class="link">términos y condiciones</a> y en la
                <a href="{{ route('legal.privacy') }}" target="_blank" class="link">política de privacidad</a>, que se ven sin ingresar.
                Conviene que un abogado revise los textos antes de alquilar el sistema.
            </p>
            <form method="POST" action="{{ route('superadmin.legal.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-input name="provider_name" label="Nombre o razón social" :value="old('provider_name', $values['provider_name'])" required hint="Quien presta el servicio y cobra el abono."/>
                <x-input name="provider_cuit" label="CUIT" :value="old('provider_cuit', $values['provider_cuit'])" inputmode="numeric" placeholder="20-12345678-9"/>
                <x-input name="provider_address" label="Domicilio" :value="old('provider_address', $values['provider_address'])"/>
                <x-input name="email" type="email" label="Correo de contacto" :value="old('email', $values['email'])" hint="Vacío: se usa el correo de soporte."/>
                <x-input name="jurisdiction" label="Tribunales para un reclamo" :value="old('jurisdiction', $values['jurisdiction'])" placeholder="los tribunales ordinarios de la Ciudad de Mendoza"
                         hint="Vacío: los del domicilio del titular."/>
                <div class="flex justify-end"><button class="btn btn-primary"><x-icon name="check" class="size-4"/> Guardar</button></div>
            </form>
        </x-panel>

        <x-panel title="Aceptación en cada galpón" :padding="false">
            <p class="px-5 pt-4 text-sm text-stone-600 dark:text-stone-400">
                El administrador de cada galpón los acepta al ingresar (versión vigente: {{ \Illuminate\Support\Carbon::parse($version)->format('d/m/Y') }}).
            </p>
            <x-table class="mt-3 border-0 shadow-none">
                <thead><tr><th>Galpón</th><th>Administrador</th><th>Aceptó</th></tr></thead>
                <tbody>
                    @forelse ($admins as $admin)
                        <tr>
                            <td>{{ $admin->warehouse?->name }}</td>
                            <td>{{ $admin->first_name }} {{ $admin->last_name }} <span class="code text-xs text-stone-500">{{ $admin->username }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($admin->terms_version === $version)
                                    <x-badge color="green">{{ fdate($admin->terms_accepted_at, true) }}</x-badge>
                                @elseif ($admin->terms_version)
                                    <x-badge color="amber">Versión anterior</x-badge>
                                @else
                                    <x-badge color="stone">Pendiente</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="3" message="Todavía no hay administradores de galpón."/>
                    @endforelse
                </tbody>
            </x-table>
        </x-panel>
    </div>
</x-layouts.app>
