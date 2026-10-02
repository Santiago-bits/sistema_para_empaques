{{-- Resumen de un cierre: snapshot congelado (show) o vista previa calculada ahora (create). --}}
<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
    <x-stat label="Cajones ingresados" :value="num($s['crates_in'] ?? 0)" icon="archive"/>
    <x-stat label="Cajones procesados" :value="num($s['crates_processed'] ?? 0)" icon="box" color="sky"/>
    <x-stat label="Kg procesados" :value="kg($s['kg_processed'] ?? 0, 0)" icon="scale" color="accent"/>
    <x-stat label="Embaladores" :value="num($s['packers'] ?? 0)" icon="users" color="violet"/>
    <x-stat label="Descartes" :value="num($s['rejects'] ?? 0).' · '.kg($s['kg_rejected'] ?? 0, 0)" icon="trash" color="red"/>
    <x-stat label="% merma" :value="pct($s['waste_pct'] ?? 0)" icon="chart-bar" color="red"/>
    <x-stat label="Cargas despachadas" :value="num($s['loads_dispatched'] ?? 0).' · '.kg($s['kg_dispatched'] ?? 0, 0)" icon="truck" color="amber"/>
    <x-stat label="Facturas / incidentes" :value="num($s['invoices'] ?? 0).' / '.num($s['incidents'] ?? 0)" icon="document" color="stone"/>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    @foreach (['by_variety' => ['Por variedad', 'Variedad'], 'by_packer' => ['Por embalador', 'Embalador']] as $key => [$title, $column])
        <x-panel :title="$title" :padding="false">
            <table class="table">
                <thead><tr><th>{{ $column }}</th><th class="num">Cajones</th><th class="num">Kg</th></tr></thead>
                <tbody>
                    @forelse ($s[$key] ?? [] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="num">{{ num($row['crates']) }}</td><td class="num">{{ kg($row['kg']) }}</td></tr>
                    @empty
                        <x-empty colspan="3" message="Sin producción ese día."/>
                    @endforelse
                </tbody>
            </table>
        </x-panel>
    @endforeach
</div>
