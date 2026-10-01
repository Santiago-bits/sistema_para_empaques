<x-layouts.app :title="$supply->exists ? 'Editar insumo' : 'Nuevo insumo'">
    <x-page-header :title="$supply->exists ? 'Editar: '.$supply->name : 'Nuevo insumo'" :back="$supply->exists ? route('supplies.show', $supply) : route('supplies.index')"/>

    <form method="POST" action="{{ $supply->exists ? route('supplies.update', $supply) : route('supplies.store') }}" class="space-y-6">
        @csrf
        @if ($supply->exists) @method('PUT') @endif
        <x-panel>
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="code" label="Código" :value="$supply->code" class="code" required/>
                <x-input name="name" label="Nombre" :value="$supply->name" required/>
                <x-input name="category" label="Categoría" :value="$supply->category" list="supply-categories" hint="Cajas, Etiquetas, Film…"/>
                <datalist id="supply-categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                <x-input name="unit" label="Unidad" :value="$supply->unit" required hint="u, rollo, kg, m…"/>
                @unless ($supply->exists)
                    <x-input name="stock" label="Stock inicial" inputmode="decimal" value="0"/>
                @endunless
                <x-input name="min_stock" label="Stock mínimo" :value="$supply->min_stock" inputmode="decimal" hint="Por debajo se genera una alerta."/>
                <x-input name="unit_cost" label="Costo unitario" :value="$supply->unit_cost" inputmode="decimal"/>
                <x-select name="provider_id" label="Proveedor" :options="$providers" :value="$supply->provider_id" placeholder="—"/>
                <div class="pt-6"><x-checkbox name="active" label="Activo" :checked="$supply->active"/></div>
            </div>
            @if ($supply->exists)
                <p class="form-hint mt-4">El stock sólo cambia con movimientos (ingreso, egreso o ajuste) para que quede todo registrado.</p>
            @endif
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ $supply->exists ? route('supplies.show', $supply) : route('supplies.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-layouts.app>
