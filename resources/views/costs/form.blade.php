<x-layouts.app :title="$cost->exists ? 'Editar costo' : 'Nuevo costo'">
    <x-page-header :title="$cost->exists ? 'Editar costo' : 'Nuevo costo'" :back="route('costs.index')"/>

    <form method="POST" action="{{ $cost->exists ? route('costs.update', $cost) : route('costs.store') }}" class="space-y-6">
        @csrf
        @if ($cost->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-2">
                <x-select name="category" label="Categoría" :options="\App\Models\Cost::CATEGORIES" :value="$cost->category" required/>
                <x-input name="date" type="date" label="Fecha" :value="$cost->date?->toDateString()" :max="today()->toDateString()" required/>
                <div class="md:col-span-2"><x-input name="description" label="Descripción" :value="$cost->description" required maxlength="255"/></div>
                <x-input name="amount" label="Importe en pesos" :value="$cost->amount !== null ? num($cost->amount, 2) : null" inputmode="decimal" required hint="Ej.: 125.000,50"/>
                <x-select name="load_id" label="Carga asociada (opcional)" :options="$loads" :value="$cost->costable_type === 'load' ? $cost->costable_id : null" placeholder="Ninguna" hint="Para fletes u otros gastos de una carga puntual."/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('costs.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
