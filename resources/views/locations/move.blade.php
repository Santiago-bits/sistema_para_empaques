<x-layouts.app title="Mover pallets y cajones">
    <x-page-header title="Mover pallets y cajones" subtitle="Escaneá el pallet o cajón, después la ubicación de destino y confirmá con Enter." :back="route('locations.index')"/>

    <div x-data="moveScreen({{ \Illuminate\Support\Js::from(['lookupUrl' => route('locations.lookup'), 'moveUrl' => route('locations.move'), 'initialCode' => $initialCode]) }})" x-init="init()"
         class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Resultado arriba de todo: visible sin desplazarse mientras se escanea. --}}
            <p x-show="message" x-cloak x-text="message" class="rounded-lg px-4 py-3 text-sm font-medium" role="status" aria-live="polite"
               :class="error ? 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'"></p>
            <div class="panel p-5">
                <label for="mv-item" class="form-label">1. Pallet o cajón</label>
                <input id="mv-item" x-ref="item" x-model.trim="itemCode" @keydown.enter.prevent="findItem()" class="form-input code py-3 text-2xl" placeholder="Escanear código" autocomplete="off">
                <template x-if="item">
                    <p class="mt-2 text-sm"><span x-text="item.type_label"></span> <strong class="code" x-text="item.code"></strong> ·
                        <span x-text="item.status"></span> · ahora en <strong x-text="item.location"></strong>
                        <span x-show="item.crates !== null" x-text="'· ' + item.crates + ' cajones'"></span></p>
                </template>
            </div>

            <div class="panel p-5">
                <label for="mv-dest" class="form-label">2. Ubicación de destino</label>
                <div class="flex gap-2">
                    <input id="mv-dest" x-ref="dest" x-model.trim="destCode" @keydown.enter.prevent="findDest()" class="form-input code py-3 text-2xl" placeholder="Escanear código de ubicación" autocomplete="off">
                </div>
                <div class="mt-3">
                    <label for="mv-select" class="form-label">o elegir de la lista</label>
                    <select id="mv-select" class="form-input" x-model="destId" @change="destFromSelect($event)">
                        <option value="">—</option>
                        @foreach ($locations as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <template x-if="dest">
                    <p class="mt-2 text-sm">Destino: <strong x-text="dest.path"></strong>
                        <span x-show="dest.capacity" x-text="'· ' + dest.pallets + '/' + dest.capacity + ' pallets (' + dest.free + ' libres)'"></span></p>
                </template>
                <div class="mt-3">
                    <label for="mv-notes" class="form-label">Nota (opcional)</label>
                    <input id="mv-notes" x-model="notes" class="form-input" maxlength="255">
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-lg w-full" @click="move()" :disabled="!item || !destId || busy">
                <x-icon name="check" class="size-5"/> Confirmar movimiento
            </button>
        </div>

        <x-panel title="Mis últimos movimientos" :padding="false">
            <ul class="divide-y divide-stone-100 text-sm dark:divide-stone-800">
                @forelse ($recent as $m)
                    <li class="px-4 py-2">
                        <p><span class="code">{{ $m->movable?->code }}</span></p>
                        <p class="text-xs text-stone-500">{{ $m->fromLocation?->name ?? '—' }} → {{ $m->toLocation?->name ?? $m->to_label }} · {{ fdate($m->moved_at, true) }}</p>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-stone-500">Sin movimientos.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>

    @push('scripts')
        <script>
            window.moveScreen = function (config) {
                return {
                    itemCode: config.initialCode || '', destCode: '', destId: '', notes: '',
                    item: null, dest: null, busy: false, message: '', error: false,
                    init() {
                        this.$refs.item.focus();
                        if (this.itemCode) this.findItem();
                    },
                    say(text, error = false) {
                        this.message = text; this.error = error;
                        error ? window.sounds.error() : window.sounds.success();
                    },
                    async findItem() {
                        if (!this.itemCode) return;
                        this.$refs.dest.focus();
                        try {
                            this.item = await window.api(config.lookupUrl + '?kind=movable&code=' + encodeURIComponent(this.itemCode));
                            this.message = '';
                        } catch (e) {
                            this.item = null;
                            this.say(e.message, true);
                            this.$refs.item.focus();
                        }
                    },
                    async findDest() {
                        if (!this.destCode) return;
                        try {
                            this.dest = await window.api(config.lookupUrl + '?kind=location&code=' + encodeURIComponent(this.destCode));
                            this.destId = String(this.dest.id);
                            this.move();
                        } catch (e) {
                            this.dest = null; this.destId = '';
                            this.say(e.message, true);
                            // Queda seleccionado para reescanear el código correcto.
                            this.$refs.dest.focus(); this.$refs.dest.select();
                        }
                    },
                    destFromSelect(e) {
                        const option = e.target.selectedOptions[0];
                        this.dest = this.destId ? { id: this.destId, path: option.text, capacity: 0 } : null;
                    },
                    async move() {
                        if (!this.item || !this.destId || this.busy) return;
                        this.busy = true;
                        try {
                            const res = await window.api(config.moveUrl, { method: 'POST', body: {
                                movable_type: this.item.type, movable_id: this.item.id, to_location_id: this.destId, notes: this.notes || null,
                            } });
                            this.say(res.message);
                            this.item = null; this.dest = null; this.itemCode = ''; this.destCode = ''; this.destId = ''; this.notes = '';
                            this.$refs.item.focus();
                        } catch (e) {
                            const first = e.errors && Object.values(e.errors)[0];
                            this.say(first ? first[0] : e.message, true);
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
