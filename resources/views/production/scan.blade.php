<x-layouts.app title="Modo escaneo">
    <x-page-header title="Modo escaneo" subtitle="Registro de producción con lector de códigos. Todo se opera con el teclado.">
        <x-slot:actions>
            @can('production.view')
                <a href="{{ route('production.index') }}" class="btn btn-secondary"><x-icon name="list" class="size-4"/> Registros</a>
            @endcan
            <a href="{{ route('kiosk') }}" class="btn btn-secondary"><x-icon name="computer" class="size-4"/> Pantalla completa (kiosco)</a>
        </x-slot:actions>
    </x-page-header>

    @include('production._scanner', ['kiosk' => false])
</x-layouts.app>
