<x-layouts.app title="Informe de embalador">
    <x-page-header title="Informe de embalador" subtitle="Elegí el embalador y el período." :back="route('reports.index')"/>
    <form method="GET" action="{{ route('reports.show', 'packer') }}" class="panel grid max-w-2xl gap-4 p-5 sm:grid-cols-3" data-allow-resubmit>
        <x-select name="packer_id" label="Embalador" :options="$packers" placeholder="Seleccionar…" required class="sm:col-span-3"/>
        <x-input name="from" type="date" label="Desde" :value="$filters->from->toDateString()"/>
        <x-input name="to" type="date" label="Hasta" :value="$filters->to->toDateString()"/>
        <div class="flex items-end"><button class="btn btn-primary w-full">Ver informe</button></div>
    </form>
</x-layouts.app>
