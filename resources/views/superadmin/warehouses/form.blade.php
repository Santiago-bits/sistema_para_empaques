<x-layouts.app :title="$warehouse->exists ? 'Editar galpón' : 'Nuevo galpón'">
    <x-page-header :title="$warehouse->exists ? 'Editar: '.$warehouse->name : 'Nuevo galpón'"
                   subtitle="El galpón nace con sus datos base (módulos, numeraciones, motivos, turnos). Después le creás su administrador general."
                   :back="$warehouse->exists ? route('superadmin.warehouses.show', $warehouse) : route('superadmin.warehouses.index')"/>

    <form method="POST" action="{{ $warehouse->exists ? route('superadmin.warehouses.update', $warehouse) : route('superadmin.warehouses.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($warehouse->exists) @method('PUT') @endif
        <x-panel title="Galpón">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2"><x-input name="name" label="Nombre del galpón" :value="$warehouse->name" required/></div>
                <x-input name="code" label="Código" :value="$warehouse->code" :placeholder="$nextCode" class="code" hint="Vacío = se genera solo."/>
                @unless ($warehouse->exists)
                    <x-input name="cuit" label="CUIT" hint="11 dígitos. Lo puede cambiar después el administrador del galpón."/>
                    <x-input name="locality" label="Localidad"/>
                @endunless
                <div class="md:col-span-{{ $warehouse->exists ? 3 : 1 }}"><x-input name="address" label="Dirección" :value="$warehouse->address"/></div>
            </div>
            @if ($warehouse->exists)
                <div class="mt-4">
                    <input type="hidden" name="active" value="0">
                    <x-checkbox name="active" label="Activo (si lo desactivás, nadie de este galpón puede entrar; sus datos se conservan)" :checked="$warehouse->active"/>
                </div>
            @else
                <input type="hidden" name="active" value="1">
            @endif
        </x-panel>

        <div class="flex justify-end gap-2">
            <a href="{{ route('superadmin.warehouses.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
