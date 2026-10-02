<x-layouts.app :title="$license->exists ? 'Editar cliente' : 'Nuevo cliente'">
    <x-page-header :title="$license->exists ? 'Editar: '.$license->client_name : 'Nuevo cliente'"
                   subtitle="Cada empaque tiene su propia instalación; con estos datos se conecta a tu Panel General."
                   :back="$license->exists ? route('central.clients.show', $license) : route('central.clients.index')"/>

    <form method="POST" action="{{ $license->exists ? route('central.clients.update', $license) : route('central.clients.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($license->exists) @method('PUT') @endif
        <x-panel title="Empaque">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2"><x-input name="client_name" label="Nombre del empaque" :value="$license->client_name" required/></div>
                <x-input name="locality" label="Localidad" :value="$license->locality"/>
                <x-input name="contact_name" label="Contacto (dueño / encargado)" :value="$license->contact_name"/>
                <x-input name="contact_phone" label="Teléfono" :value="$license->contact_phone"/>
                <x-input name="contact_email" type="email" label="Email" :value="$license->contact_email"/>
                <x-input name="installation_id" label="ID de instalación" :value="$license->installation_id" class="code"
                         :hint="$license->exists ? 'Cambiarlo obliga a actualizar el .env del empaque.' : 'Vacío = se genera solo.'"/>
            </div>
        </x-panel>

        <x-panel title="Licencia">
            <div class="grid gap-4 md:grid-cols-4">
                <x-select name="plan" label="Plan" :options="\App\Http\Controllers\Developer\LicenseController::PLANS" :value="$license->plan" required/>
                <x-select name="status" label="Estado" :options="\App\Http\Controllers\Developer\LicenseController::STATUSES" :value="$license->status" required/>
                <x-input name="starts_on" type="date" label="Inicio" :value="$license->starts_on" required/>
                <x-input name="expires_on" type="date" label="Vencimiento" :value="$license->expires_on" hint="Vacío = sin vencimiento."/>
            </div>
            <p class="form-hint mt-3">Una licencia vencida o suspendida nunca bloquea datos ni la operación del empaque: sólo le muestra un aviso.</p>
            <div class="mt-4">
                <span class="form-label">Módulos contratados</span>
                <div class="mt-1 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Services\ModuleService::CATALOG as $key => [$name])
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, (array) old('modules', $license->modules ?? []), true))> {{ $name }}</label>
                    @endforeach
                </div>
            </div>
            <div class="mt-4"><x-textarea name="notes" label="Notas internas" :value="$license->notes"/></div>
        </x-panel>

        <div class="flex justify-end gap-2">
            <a href="{{ route('central.clients.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
