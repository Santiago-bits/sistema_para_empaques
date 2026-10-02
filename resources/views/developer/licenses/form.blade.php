@php
    $selectedModules = old('modules', $license->modules ?? []);
@endphp
<x-layouts.app :title="$license->exists ? 'Editar licencia' : 'Nueva licencia'">
    <x-page-header :title="$license->exists ? 'Editar licencia' : 'Nueva licencia'" :back="route('developer.licenses.index')"/>

    <form method="POST" action="{{ $license->exists ? route('developer.licenses.update', $license) : route('developer.licenses.store') }}" class="space-y-6">
        @csrf
        @if ($license->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-2">
                <x-input name="installation_id" label="ID de instalación" :value="$license->installation_id" required class="code" hint="GALPON_INSTALLATION_ID del .env de esa instalación."/>
                <x-input name="client_name" label="Cliente" :value="$license->client_name" required/>
                <x-select name="plan" label="Plan" :options="\App\Http\Controllers\Developer\LicenseController::PLANS" :value="$license->plan" required/>
                <x-select name="status" label="Estado" :options="\App\Http\Controllers\Developer\LicenseController::STATUSES" :value="$license->status" required/>
                <x-input name="starts_on" type="date" label="Inicio" :value="$license->starts_on?->toDateString()" required/>
                <x-input name="expires_on" type="date" label="Vencimiento" :value="$license->expires_on?->toDateString()" hint="Vacío = sin vencimiento."/>
            </div>
            <fieldset class="mt-5">
                <legend class="form-label">Módulos incluidos</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Services\ModuleService::CATALOG as $key => $module)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, $selectedModules, true)) class="rounded border-stone-300 dark:border-stone-600 dark:bg-stone-900">
                            {{ $module[0] }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="mt-5"><x-textarea name="notes" label="Notas" :value="$license->notes" rows="2"/></div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('developer.licenses.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
