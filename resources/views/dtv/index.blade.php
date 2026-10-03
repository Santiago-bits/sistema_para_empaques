<x-layouts.app title="DTV-e">
    <x-page-header title="Registro de DTV-e (SENASA)" subtitle="Ingresos y egresos de fruta con su documento de tránsito, kilos y saldo por variedad.">
        <x-slot:actions>
            @can('dtv.manage')
                <a href="{{ route('dtv.create', ['direction' => 'in']) }}" class="btn btn-secondary"><x-icon name="arrow-down" class="size-4"/> Nuevo ingreso</a>
                <a href="{{ route('dtv.create', ['direction' => 'out']) }}" class="btn btn-primary"><x-icon name="arrow-up" class="size-4"/> Nuevo egreso</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('import_errors'))
        <div class="panel mb-6 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
            <p class="font-semibold">Algunas filas de la planilla no se pudieron leer:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Documentos" :value="num($totals->documents ?? 0)" icon="document"/>
        <x-stat label="Kg que entraron" :value="kg($totals->kg_in ?? 0, 0)" icon="arrow-down" color="sky"/>
        <x-stat label="Kg que salieron" :value="kg($totals->kg_out ?? 0, 0)" icon="arrow-up" color="amber"/>
        <x-stat label="Saldo" :value="kg(($totals->kg_in ?? 0) - ($totals->kg_out ?? 0), 0)" icon="scale" :color="(($totals->kg_in ?? 0) - ($totals->kg_out ?? 0)) < 0 ? 'red' : 'brand'"/>
    </div>

    <x-filters :exports="[['label' => 'Excel', 'format' => 'xlsx', 'route' => route('dtv.index')], ['label' => 'CSV', 'format' => 'csv', 'route' => route('dtv.index')]]">
        <x-input name="q" label="Buscar" :value="request('q')" placeholder="N° DTV, destinatario, destino, transporte"/>
        <x-select name="direction" label="Ingreso / egreso" :options="\App\Models\DtvDocument::DIRECTIONS" :value="request('direction')" placeholder="Todos"/>
        <x-select name="species" label="Especie" :options="$species" :value="request('species')" placeholder="Todas"/>
        <x-input name="from" type="date" label="Desde" :value="request('from')"/>
        <x-input name="to" type="date" label="Hasta" :value="request('to')"/>
    </x-filters>

    <x-table class="mb-8">
        <thead><tr><th>Fecha</th><th>E / I</th><th>N° DTV-e</th><th>Tipo</th><th>Destinatario</th><th>Destino</th><th>Especie</th><th>Variedad</th><th class="num">Cant.</th><th>Unidad</th><th class="num">Kg</th><th class="num">Kg totales</th><th>Transporte</th></tr></thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ fdate(\Illuminate\Support\Carbon::parse($line->date)) }}</td>
                    <td><x-badge :color="$line->direction === 'out' ? 'red' : 'green'">{{ $line->direction === 'out' ? 'Egreso' : 'Ingreso' }}</x-badge></td>
                    <td><a href="{{ route('dtv.show', $line->dtv_document_id) }}" class="code link">{{ $line->number }}</a></td>
                    <td class="text-sm">{{ $line->doc_type }}</td>
                    <td class="text-sm">{{ $line->recipient }}</td>
                    <td class="text-sm">{{ $line->destination }}</td>
                    <td class="text-sm">{{ $line->species }}</td>
                    <td class="text-sm">{{ $line->variety_label ?? $line->variety_name }}</td>
                    <td class="num">{{ num($line->quantity, 2) }}</td>
                    <td class="text-sm">{{ $line->unit }}</td>
                    <td class="num">{{ $line->kg_per_unit !== null ? num($line->kg_per_unit, 2) : '—' }}</td>
                    <td @class(['num font-medium', 'text-red-600 dark:text-red-400' => $line->direction === 'out'])>{{ ($line->direction === 'out' ? '−' : '').num($line->kg_total, 2) }}</td>
                    <td class="text-sm">{{ $line->transport }}</td>
                </tr>
            @empty
                <x-empty :colspan="13" message="Todavía no hay DTV-e cargados. Cargá uno nuevo o importá tu planilla de Excel."/>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $lines->links() }}</x-slot:footer>
    </x-table>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-panel title="Saldo de kilos por variedad" class="lg:col-span-2" :padding="false">
            <x-table class="border-0 shadow-none">
                <thead><tr><th>Especie</th><th>Variedad</th><th class="num">Entró (kg)</th><th class="num">Salió (kg)</th><th class="num">Saldo (kg)</th></tr></thead>
                <tbody>
                    @forelse ($balances as $b)
                        <tr>
                            <td>{{ $b->species ?: '—' }}</td>
                            <td>{{ $b->variety ?: '—' }}</td>
                            <td class="num">{{ num($b->kg_in, 2) }}</td>
                            <td class="num">{{ num($b->kg_out, 2) }}</td>
                            <td @class(['num font-semibold', 'text-red-600 dark:text-red-400' => $b->balance < 0])>{{ num($b->balance, 2) }}</td>
                        </tr>
                    @empty
                        <x-empty :colspan="5" message="Sin movimientos en el período."/>
                    @endforelse
                </tbody>
            </x-table>
        </x-panel>

        @can('dtv.manage')
            <x-panel title="Importar mi planilla de Excel">
                <p class="text-sm text-stone-600 dark:text-stone-400">
                    Subí la planilla donde llevás los DTV-e (la hoja «DTV-e» tiene que ser la primera). Se buscan solas las columnas
                    FECHA, E / I, N° DTV-e, TIPO, EMISOR, ESTABLECIMIENTO, DESTINATARIO, DESTINO, ESPECIE, VARIEDAD, CANT., UNIDAD, KG,
                    KG TOTALES y TRANSPORTE. Los DTV-e que ya estén cargados no se repiten.
                </p>
                <form method="POST" action="{{ route('dtv.import') }}" enctype="multipart/form-data" class="mt-3 space-y-3" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv" required class="form-input w-full">
                    @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="btn btn-primary w-full" :disabled="busy"><x-icon name="upload" class="size-4"/> <span x-text="busy ? 'Importando…' : 'Subir planilla'">Subir planilla</span></button>
                </form>
            </x-panel>
        @endcan
    </div>
</x-layouts.app>
