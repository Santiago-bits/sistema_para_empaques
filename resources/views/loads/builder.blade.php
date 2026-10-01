<x-layouts.app :title="'Armado · '.$load->number">
    <x-page-header :title="'Armado de carga '.$load->number" :subtitle="($load->client?->business_name ?? 'Sin cliente').' · '.($load->destination?->name ?? 'Sin destino').' · '.($load->truck?->plate ?? 'Sin camión')" :back="route('loads.show', $load)">
        <x-slot:actions>
            @can('loads.close')
                <a href="{{ route('loads.close.show', $load) }}" class="btn btn-primary"><x-icon name="lock" class="size-4"/> Revisar y cerrar</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div x-data="loadBuilder({{ \Illuminate\Support\Js::from($config) }})" x-init="init()" class="grid gap-6 xl:grid-cols-2">
        {{-- Disponibles --}}
        <section class="space-y-4">
            <div class="panel p-4">
                <label for="lb-scan" class="form-label">Escanear cajón o pallet</label>
                <input id="lb-scan" x-ref="scan" x-model.trim="scanCode" @keydown.enter.prevent="scan()" class="form-input code py-3 text-xl"
                       placeholder="Código de cajón o pallet + Enter" autocomplete="off">
            </div>

            <div class="panel p-4">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <x-select name="f_variety" label="Variedad" :options="$varieties" placeholder="Todas" x-model="filters.variety_id"/>
                    <x-select name="f_size" label="Tamaño" :options="$sizes" placeholder="Todos" x-model="filters.size_id"/>
                    <x-select name="f_lot" label="Lote" :options="$lots" placeholder="Todos" x-model="filters.lot_id"/>
                    <x-select name="f_producer" label="Productor" :options="$producers" placeholder="Todos" x-model="filters.producer_id"/>
                    <x-select name="f_owner" label="Propietario" :options="$owners" placeholder="Todos" x-model="filters.owner_id"/>
                    <x-select name="f_status" label="Calidad" :options="['approved' => 'Sólo aprobados', 'processed' => 'Sólo procesados']" placeholder="Procesados y aprobados" x-model="filters.status"/>
                    <x-input name="f_wmin" label="Peso mín." inputmode="decimal" x-model="filters.weight_min"/>
                    <x-input name="f_wmax" label="Peso máx." inputmode="decimal" x-model="filters.weight_max"/>
                    <x-input name="f_from" type="date" label="Procesado desde" x-model="filters.date_from"/>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" @click="page = 1; loadAvailable()"><x-icon name="filter" class="size-4"/> Buscar</button>
                    <span class="text-sm text-stone-500">Disponibles: <strong class="tabular-nums" x-text="fmtInt(available.total)"></strong> cajones · <span class="tabular-nums" x-text="fmt(available.kg, 1)"></span> kg</span>
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 border-b border-stone-200 p-3 dark:border-stone-800">
                    <button type="button" class="btn btn-primary btn-sm" :disabled="!selected.length || busy" @click="assign({ ids: selected })">
                        Agregar seleccionados (<span x-text="selected.length"></span>)
                    </button>
                    <span class="mx-1 text-stone-300">|</span>
                    <label class="text-sm" for="lb-take">Agregar los primeros</label>
                    <input id="lb-take" type="number" min="1" max="2000" x-model.number="take" class="form-input w-24 py-1 text-sm">
                    <button type="button" class="btn btn-secondary btn-sm" :disabled="!take || busy" @click="assign({ take, filters })">Agregar (FIFO)</button>
                </div>
                <div class="max-h-[50vh] overflow-y-auto">
                    <table class="table">
                        <thead><tr>
                            <th class="w-8"><input type="checkbox" @change="toggleAll($event)" aria-label="Seleccionar todos" class="size-4 rounded"></th>
                            <th>Cajón</th><th>Variedad</th><th>Tamaño</th><th>Lote</th><th class="num">Kg</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="c in available.data" :key="c.id">
                                <tr>
                                    <td><input type="checkbox" :value="c.id" x-model.number="selected" class="size-4 rounded" :aria-label="'Seleccionar ' + c.code"></td>
                                    <td class="code" x-text="c.code"></td>
                                    <td x-text="c.variety || '—'"></td>
                                    <td x-text="c.size || '—'"></td>
                                    <td class="code" x-text="c.lot || '—'"></td>
                                    <td class="num" x-text="fmt(c.weight)"></td>
                                </tr>
                            </template>
                            <tr x-show="!available.data.length"><td colspan="6" class="py-10 text-center text-sm text-stone-500">No hay cajones disponibles con esos filtros.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-stone-200 p-3 text-sm dark:border-stone-800">
                    <span class="text-stone-500">Página <span x-text="available.page"></span> de <span x-text="available.last_page"></span></span>
                    <div class="flex gap-1">
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="available.page <= 1" @click="page--; loadAvailable()">‹</button>
                        <button type="button" class="btn btn-secondary btn-sm" :disabled="available.page >= available.last_page" @click="page++; loadAvailable()">›</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Contenido de la carga --}}
        <section class="space-y-4">
            <div class="panel p-4">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div><p class="text-xs tracking-wide text-stone-500 uppercase">Cajones</p><p class="text-3xl font-bold tabular-nums" x-text="fmtInt(content.total_crates)"></p></div>
                    <div><p class="text-xs tracking-wide text-stone-500 uppercase">Kg</p><p class="text-3xl font-bold tabular-nums" x-text="fmt(content.total_kg, 1)"></p></div>
                    <div><p class="text-xs tracking-wide text-stone-500 uppercase">Pallets</p><p class="text-3xl font-bold tabular-nums" x-text="content.summary ? content.summary.pallets : 0"></p></div>
                </div>
                <template x-if="content.planned">
                    <div class="mt-3">
                        <div class="mb-1 flex justify-between text-xs text-stone-500"><span>Avance sobre lo previsto (<span x-text="fmtInt(content.planned)"></span>)</span><span x-text="Math.min(100, Math.round(content.total_crates / content.planned * 100)) + ' %'"></span></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-800"><div class="h-full rounded-full bg-brand-600" :style="`width: ${Math.min(100, content.total_crates / content.planned * 100)}%`"></div></div>
                    </div>
                </template>
                <div class="mt-3 grid gap-3 text-xs sm:grid-cols-2" x-show="content.summary">
                    <ul><template x-for="v in (content.summary ? content.summary.by_variety : [])" :key="'v' + v.id"><li class="flex justify-between"><span x-text="v.name"></span><span class="tabular-nums" x-text="fmtInt(v.crates)"></span></li></template></ul>
                    <ul><template x-for="s in (content.summary ? content.summary.by_size : [])" :key="'s' + s.id"><li class="flex justify-between"><span x-text="s.name"></span><span class="tabular-nums" x-text="fmtInt(s.crates)"></span></li></template></ul>
                </div>
            </div>

            <div x-show="message" class="rounded-lg px-4 py-3 text-sm" :class="error ? 'bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200' : 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'">
                <p class="font-medium" x-text="message"></p>
                <ul class="mt-1 max-h-32 list-inside list-disc overflow-y-auto text-xs" x-show="rejected.length">
                    <template x-for="r in rejected" :key="(r.crate_id || '') + (r.code || '')"><li><span class="font-mono" x-text="r.code || ('#' + r.crate_id)"></span>: <span x-text="r.reason"></span></li></template>
                </ul>
            </div>

            <div class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-stone-200 p-3 dark:border-stone-800">
                    <p class="text-sm font-semibold">En la carga <span class="text-xs font-normal text-stone-500">(se actualiza sola)</span></p>
                    <button type="button" class="btn btn-secondary btn-sm text-red-600" :disabled="!toRemove.length || busy" @click="remove()">Quitar seleccionados (<span x-text="toRemove.length"></span>)</button>
                </div>
                <div class="max-h-[50vh] overflow-y-auto">
                    <table class="table">
                        <thead><tr><th class="w-8"></th><th>Cajón</th><th>Variedad</th><th>Tamaño</th><th>Pallet</th><th class="num">Kg</th></tr></thead>
                        <tbody>
                            <template x-for="c in content.crates" :key="c.id">
                                <tr>
                                    <td><input type="checkbox" :value="c.id" x-model.number="toRemove" class="size-4 rounded" :aria-label="'Quitar ' + c.code"></td>
                                    <td class="code" x-text="c.code"></td>
                                    <td x-text="c.variety || '—'"></td>
                                    <td x-text="c.size || '—'"></td>
                                    <td class="code" x-text="c.pallet || '—'"></td>
                                    <td class="num" x-text="fmt(c.weight)"></td>
                                </tr>
                            </template>
                            <tr x-show="!content.crates.length"><td colspan="6" class="py-10 text-center text-sm text-stone-500">Todavía no hay cajones en la carga.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p x-show="content.more" class="border-t border-stone-200 px-4 py-2 text-xs text-stone-500 dark:border-stone-800">Se muestran los últimos 100 cajones agregados.</p>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            window.loadBuilder = function (config) {
                return {
                    filters: { variety_id: '', size_id: '', lot_id: '', producer_id: '', owner_id: '', status: '', weight_min: '', weight_max: '', date_from: '' },
                    page: 1,
                    available: { data: [], page: 1, last_page: 1, total: 0, kg: 0 },
                    content: { crates: [], total_crates: 0, total_kg: 0, planned: null, summary: null, more: false, status: 'draft' },
                    selected: [], toRemove: [], take: 50, scanCode: '',
                    busy: false, message: '', error: false, rejected: [],
                    timer: null,

                    init() {
                        this.loadAvailable();
                        this.loadContent();
                        // Refresco en vivo: muestra cambios hechos por otros usuarios en otras PCs.
                        this.timer = setInterval(() => { if (!this.busy && !document.hidden) { this.loadContent(); } }, 8000);
                        this.$refs.scan.focus();
                    },
                    fmt(n, d = 2) { return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d }); },
                    fmtInt(n) { return Number(n || 0).toLocaleString('es-AR'); },
                    query() {
                        const p = new URLSearchParams({ page: this.page });
                        Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p.set(k, v); });
                        return p.toString();
                    },
                    async loadAvailable() {
                        const seq = ++this.availableSeq;
                        try {
                            const data = await window.api(config.availableUrl + '?' + this.query());
                            if (seq !== this.availableSeq) return;
                            this.available = data;
                            this.selected = this.selected.filter(id => this.available.data.some(c => c.id === id));
                        } catch (e) { this.notify(e.message, true); }
                    },
                    contentSeq: 0,
                    availableSeq: 0,
                    async loadContent() {
                        // Sólo se aplica la respuesta más reciente (un refresco viejo no pisa uno nuevo).
                        const seq = ++this.contentSeq;
                        try {
                            const data = await window.api(config.contentUrl);
                            if (seq !== this.contentSeq) return;
                            if (data.status !== 'draft') { window.location = config.showUrl; return; }
                            this.content = data;
                            this.toRemove = this.toRemove.filter(id => data.crates.some(c => c.id === id));
                        } catch (e) { /* el latido de conexión informa si se perdió la red */ }
                    },
                    toggleAll(e) {
                        this.selected = e.target.checked ? this.available.data.map(c => c.id) : [];
                    },
                    notify(text, error = false, rejected = []) {
                        this.message = text; this.error = error; this.rejected = rejected;
                        error ? window.sounds.error() : (rejected.length ? window.sounds.duplicate() : window.sounds.success());
                    },
                    async assign(body) {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            const res = await window.api(config.assignUrl, { method: 'POST', body });
                            this.notify(res.message, res.assigned === 0 && res.rejected.length > 0, res.rejected);
                            this.selected = [];
                            await Promise.all([this.loadAvailable(), this.loadContent()]);
                        } catch (e) {
                            this.notify(e.message, true);
                        } finally {
                            this.busy = false;
                        }
                    },
                    async scan() {
                        const code = this.scanCode;
                        if (!code) return;
                        this.scanCode = '';
                        this.$refs.scan.focus();
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            let res = await window.api(config.assignUrl, { method: 'POST', body: { codes: [code] } });
                            // Si no es un cajón, se intenta como pallet completo.
                            if (res.assigned === 0 && res.rejected.length && res.rejected[0].reason === 'Código inexistente.') {
                                try {
                                    res = await window.api(config.assignUrl, { method: 'POST', body: { pallet_code: code } });
                                } catch (e) {
                                    this.notify('No existe un cajón ni un pallet con el código ' + code + '.', true);
                                    return;
                                }
                            }
                            this.notify(res.message, res.assigned === 0, res.rejected);
                            await Promise.all([this.loadAvailable(), this.loadContent()]);
                        } catch (e) {
                            this.notify(e.message, true);
                        } finally {
                            this.busy = false;
                        }
                    },
                    async remove() {
                        if (this.busy || !this.toRemove.length) return;
                        if (!window.confirm('¿Quitar ' + this.toRemove.length + ' cajón/es de la carga?')) return;
                        this.busy = true;
                        try {
                            const res = await window.api(config.removeUrl, { method: 'POST', body: { ids: this.toRemove } });
                            this.notify(res.message, false, res.rejected);
                            this.toRemove = [];
                            await Promise.all([this.loadAvailable(), this.loadContent()]);
                        } catch (e) {
                            this.notify(e.message, true);
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
