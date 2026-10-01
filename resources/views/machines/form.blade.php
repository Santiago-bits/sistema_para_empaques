<x-layouts.app :title="$machine->exists ? 'Editar máquina' : 'Nueva máquina'">
    <x-page-header :title="$machine->exists ? 'Editar: '.$machine->name : 'Nueva máquina'" :back="$machine->exists ? route('machines.show', $machine) : route('machines.index')"/>

    <form method="POST" action="{{ $machine->exists ? route('machines.update', $machine) : route('machines.store') }}" class="space-y-6">
        @csrf
        @if ($machine->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="code" label="Código" :value="$machine->code" class="code" required/>
                <x-input name="name" label="Nombre" :value="$machine->name" required/>
                <x-select name="status" label="Estado" :options="$statuses" :value="$machine->status" required/>
                <x-input name="brand" label="Marca" :value="$machine->brand"/>
                <x-input name="model" label="Modelo" :value="$machine->model"/>
                <x-input name="serial_number" label="Número de serie" :value="$machine->serial_number"/>
                <x-select name="location_id" label="Ubicación" :options="$locations" :value="$machine->location_id" placeholder="—"/>
                <x-input name="next_maintenance_on" type="date" label="Próximo mantenimiento" :value="$machine->next_maintenance_on"/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $machine->exists ? route('machines.show', $machine) : route('machines.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
