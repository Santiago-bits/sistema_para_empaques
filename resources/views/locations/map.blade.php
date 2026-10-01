<x-layouts.app title="Mapa del galpón">
    <x-page-header title="Mapa del galpón" subtitle="Ocupación por sector, cámara y zona. Tocá una ubicación para ver qué contiene.">
        <x-slot:actions>
            <a href="{{ route('locations.index') }}" class="btn btn-secondary"><x-icon name="list" class="size-4"/> Ubicaciones</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Capacidad" :value="num($capacity['capacity']).' pallets'" icon="pallet"/>
        <x-stat label="Ocupados" :value="num($capacity['pallets'])" icon="box" color="accent"/>
        <x-stat label="Libres" :value="num($capacity['free'])" icon="check" color="sky"/>
        <x-stat label="Ocupación" :value="pct($capacity['occupancy_pct'])" icon="chart-bar" color="violet"/>
    </div>

    <div x-data="warehouseMap({{ \Illuminate\Support\Js::from(['items' => $items, 'cols' => $cols, 'rows' => $rows, 'saveUrl' => route('locations.map.update'), 'canEdit' => auth()->user()->can('locations.manage'), 'contentBase' => url('ubicaciones')]) }})"
         class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <x-panel :padding="false">
            <x-slot:actions>
                <div class="flex items-center gap-3 text-xs text-stone-500">
                    <span class="flex items-center gap-1"><span class="size-3 rounded bg-emerald-500/70"></span> &lt; 60 %</span>
                    <span class="flex items-center gap-1"><span class="size-3 rounded bg-amber-500/70"></span> 60–90 %</span>
                    <span class="flex items-center gap-1"><span class="size-3 rounded bg-red-500/70"></span> &gt; 90 %</span>
                    <template x-if="canEdit">
                        <button type="button" class="btn btn-secondary btn-sm" @click="editing = !editing" x-text="editing ? 'Terminar edición' : 'Editar mapa'"></button>
                    </template>
                </div>
            </x-slot:actions>
            <div class="overflow-auto p-4">
                <div class="relative grid gap-1 rounded-lg bg-stone-100 p-1 dark:bg-stone-950"
                     :style="`grid-template-columns: repeat(${cols}, minmax(28px, 1fr)); grid-template-rows: repeat(${rows}, 34px); min-width: ${cols * 30}px`">
                    <template x-for="item in placed" :key="item.id">
                        <button type="button" @click="select(item)"
                                class="flex flex-col items-start justify-between overflow-hidden rounded-md border p-1.5 text-left text-xs transition hover:ring-2 hover:ring-brand-500"
                                :class="[colorFor(item), selected && selected.id === item.id ? 'ring-2 ring-brand-500' : '']"
                                :style="`grid-column: ${item.x} / span ${item.w}; grid-row: ${item.y} / span ${item.h}`">
                            <span class="w-full truncate font-semibold" x-text="item.name"></span>
                            <span class="tabular-nums opacity-80" x-text="item.capacity ? item.pallets + '/' + item.capacity : item.pallets + ' pallets'"></span>
                        </button>
                    </template>
                </div>
                <p x-show="placed.length === 0" class="py-10 text-center text-sm text-stone-500">
                    Todavía no hay ubicaciones ubicadas en el mapa. Usá "Editar mapa" para asignarles posición.
                </p>
            </div>
        </x-panel>

        <div class="space-y-4">
            {{-- Contenido de la ubicación seleccionada --}}
            <x-panel title="Contenido">
                <template x-if="!detail">
                    <p class="text-sm text-stone-500">Seleccioná una ubicación del mapa.</p>
                </template>
                <template x-if="detail">
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="font-semibold" x-text="detail.name"></p>
                            <p class="text-xs text-stone-500" x-text="detail.path + ' · ' + detail.type"></p>
                        </div>
                        <p class="tabular-nums" x-text="(detail.stats.total || 0) + ' pallets' + (detail.stats.capacity ? ' de ' + detail.stats.capacity + ' (' + detail.stats.free + ' libres)' : '')"></p>
                        <ul class="max-h-72 divide-y divide-stone-100 overflow-y-auto dark:divide-stone-800">
                            <template x-for="p in detail.pallets" :key="p.code">
                                <li class="flex justify-between py-1.5"><span class="code" x-text="p.code"></span><span class="text-xs text-stone-500" x-text="[p.variety, p.crates + ' cajones'].filter(Boolean).join(' · ')"></span></li>
                            </template>
                            <template x-for="c in detail.crates" :key="c.code">
                                <li class="flex justify-between py-1.5"><span class="code" x-text="c.code"></span><span class="text-xs text-stone-500" x-text="c.variety || ''"></span></li>
                            </template>
                        </ul>
                        <a :href="detail.url" class="link text-sm">Abrir ficha completa</a>
                    </div>
                </template>
            </x-panel>

            {{-- Editor de posiciones --}}
            <template x-if="editing">
                <x-panel title="Posiciones en la grilla">
                    <p class="mb-3 text-xs text-stone-500">Columna (X), fila (Y), ancho y alto en celdas. Vacío = fuera del mapa.</p>
                    <div class="max-h-96 space-y-2 overflow-y-auto">
                        <template x-for="item in items" :key="item.id">
                            <div class="grid grid-cols-[1fr_repeat(4,44px)] items-center gap-1 text-xs">
                                <span class="truncate" x-text="item.name"></span>
                                <input type="number" min="1" class="form-input px-1 py-1 text-xs" x-model.number="item.x" aria-label="X">
                                <input type="number" min="1" class="form-input px-1 py-1 text-xs" x-model.number="item.y" aria-label="Y">
                                <input type="number" min="1" class="form-input px-1 py-1 text-xs" x-model.number="item.w" aria-label="Ancho">
                                <input type="number" min="1" class="form-input px-1 py-1 text-xs" x-model.number="item.h" aria-label="Alto">
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn btn-primary mt-4 w-full" @click="save()" :disabled="saving">Guardar mapa</button>
                    <p class="mt-2 text-xs" x-text="saveMessage"></p>
                </x-panel>
            </template>
        </div>
    </div>

    @push('scripts')
        <script>
            window.warehouseMap = function (config) {
                return {
                    items: config.items.map(i => Object.assign({}, i)),
                    cols: config.cols,
                    rows: config.rows,
                    canEdit: config.canEdit,
                    editing: false,
                    saving: false,
                    saveMessage: '',
                    selected: null,
                    detail: null,
                    get placed() {
                        return this.items.filter(i => i.x && i.y);
                    },
                    colorFor(item) {
                        if (!item.capacity) return 'border-stone-300 bg-white text-stone-700 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-200';
                        if (item.pct > 90) return 'border-red-400 bg-red-500/20 text-red-900 dark:text-red-200';
                        if (item.pct >= 60) return 'border-amber-400 bg-amber-500/20 text-amber-900 dark:text-amber-200';
                        return 'border-emerald-400 bg-emerald-500/15 text-emerald-900 dark:text-emerald-200';
                    },
                    async select(item) {
                        this.selected = item;
                        try {
                            this.detail = await window.api(config.contentBase + '/' + item.id + '/contenido');
                        } catch (e) {
                            this.detail = null;
                        }
                    },
                    async save() {
                        this.saving = true;
                        try {
                            const positions = this.items.map(i => ({ id: i.id, x: i.x || null, y: i.y || null, w: i.w || 2, h: i.h || 2 }));
                            const res = await window.api(config.saveUrl, { method: 'PUT', body: { positions } });
                            this.saveMessage = res.message;
                            this.cols = Math.max(24, ...this.placed.map(i => i.x + i.w - 1));
                            this.rows = Math.max(12, ...this.placed.map(i => i.y + i.h - 1));
                        } catch (e) {
                            this.saveMessage = e.message;
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
