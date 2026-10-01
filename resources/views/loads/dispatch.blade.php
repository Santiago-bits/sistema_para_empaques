<x-layouts.app :title="'Despacho '.$load->number">
    <x-page-header :title="'Checklist de despacho · '.$load->number" subtitle="Cada control queda registrado con el usuario y la hora." :back="route('loads.show', $load)">
        <x-slot:actions><x-status :status="$load->status" class="text-sm"/></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Datos a verificar">
            <x-dl class="!grid-cols-1" :items="[
                'Camión / patente' => $load->truck ? $load->truck->plate.' — '.trim($load->truck->brand.' '.$load->truck->model) : null,
                'Chofer' => $load->driver ? $load->driver->full_name.' · DNI '.$load->driver->dni.($load->driver->licenseExpired() ? ' · LICENCIA VENCIDA' : '') : null,
                'Destino' => $load->destination?->name,
                'Cliente' => $load->client?->business_name,
                'Cantidad' => num($load->total_crates).' cajones',
                'Peso' => kg($load->total_kg, 1),
                'Remito' => $remito?->number ?? 'No emitido',
            ]"/>
            @if (! $remito && $load->status->value === 'closed')
                @can('remitos.create')
                    <form method="POST" action="{{ route('remitos.store', $load) }}" class="mt-4">
                        @csrf
                        <button class="btn btn-secondary w-full"><x-icon name="document" class="size-4"/> Emitir remito</button>
                    </form>
                @endcan
            @endif
        </x-panel>

        <x-panel title="Controles" :padding="false" class="lg:col-span-2">
            <ul class="divide-y divide-stone-100 dark:divide-stone-800">
                @foreach ($items as $key => $label)
                    @php $check = $checks->get($key); $done = (bool) $check?->checked; @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p @class(['font-medium', 'text-emerald-700 dark:text-emerald-400' => $done])>{{ $done ? '✔' : '☐' }} {{ $label }}</p>
                            @if ($check)
                                <p class="text-xs text-stone-500">{{ $done ? 'Controlado' : 'Desmarcado' }} por {{ $check->user?->full_name }} · {{ fdate($check->checked_at, true) }} {{ $check->notes ? '· '.$check->notes : '' }}</p>
                            @endif
                        </div>
                        @if ($load->status->value === 'closed')
                            <form method="POST" action="{{ route('loads.dispatch.check', $load) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="item" value="{{ $key }}">
                                <input type="hidden" name="checked" value="{{ $done ? 0 : 1 }}">
                                <button @class(['btn btn-sm', 'btn-secondary' => $done, 'btn-primary' => ! $done])>{{ $done ? 'Desmarcar' : 'Controlado' }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($load->status->value === 'closed')
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 p-4 dark:border-stone-800">
                    <p class="text-sm {{ $pending ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                        {{ $pending ? 'Faltan '.count($pending).' control(es).' : 'Todos los controles realizados.' }}
                    </p>
                    <form method="POST" action="{{ route('loads.dispatch', $load) }}" x-data x-confirm="¿Despachar la carga {{ $load->number }}? Los cajones salen del galpón.">
                        @csrf
                        <button class="btn btn-primary btn-lg" @disabled($pending || ! $remito)><x-icon name="truck" class="size-5"/> Despachar</button>
                    </form>
                </div>
            @endif
        </x-panel>
    </div>
</x-layouts.app>
