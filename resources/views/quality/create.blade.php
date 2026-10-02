<x-layouts.app title="Control de calidad">
    <x-page-header title="Control de calidad" subtitle="Escaneá un cajón, pallet o lote · F2 aprobado · F3 rechazado · F4 observado · Ctrl+Enter registra"
                   :back="route('quality.index')"/>

    @php
        $config = ['lookupUrl' => route('quality.lookup'), 'storeUrl' => route('quality.store'), 'initialCode' => $initialCode];
    @endphp

    <div x-data="qualityScan(@js($config))" x-init="init()" data-local-keys="F2 F3 F4 Ctrl+Enter" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- 1. Código --}}
            <x-panel title="1. Código del cajón, pallet o lote">
                <form @submit.prevent="lookup()" class="flex gap-3" data-allow-resubmit>
                    <input id="qc-code" data-scan x-ref="code" x-model="code" type="text" autocomplete="off" autofocus
                           class="form-input min-w-0 flex-1 py-3 font-mono text-2xl" placeholder="Escanear código…" aria-label="Código">
                    <x-scan-camera target="qc-code" title="Escanear cajón, pallet o lote"/>
                    <button type="submit" class="btn btn-secondary btn-lg" :disabled="loading"><x-icon name="search" class="size-5"/> Buscar</button>
                </form>
                {{-- Resultado de la última operación junto al campo (visible sin desplazarse en tablet/celular). --}}
                <p x-show="message" x-cloak class="mt-3 rounded-lg px-3 py-2 text-sm font-medium" role="status" aria-live="polite"
                   :class="messageType === 'error' ? 'bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200' : 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'"
                   x-text="message"></p>
                <template x-if="target">
                    <div class="mt-4 rounded-lg border border-stone-200 bg-stone-50 p-4 dark:border-stone-700 dark:bg-stone-800/50">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-stone-500 dark:text-stone-400" x-text="target.type_label"></span>
                            <span class="code text-xl font-semibold text-stone-900 dark:text-white" x-text="target.code"></span>
                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-300" x-text="target.status_label"></span>
                            <template x-if="target.type === 'crate' && !target.eligible">
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    Sólo se aprueban/rechazan cajones procesados o en control
                                </span>
                            </template>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                            <template x-for="(value, label) in target.details" :key="label">
                                <div>
                                    <dt class="text-xs text-stone-500 uppercase dark:text-stone-400" x-text="label"></dt>
                                    <dd class="text-stone-900 dark:text-stone-100" x-text="value"></dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </template>
            </x-panel>

            {{-- 2. Resultado y mediciones --}}
            <x-panel title="2. Resultado">
                <div class="grid grid-cols-3 gap-3">
                    <button type="button" @click="setResult('approved')"
                            :class="form.result === 'approved' ? 'ring-4 ring-emerald-500/40 bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'"
                            class="rounded-xl px-4 py-5 text-lg font-semibold transition">Aprobado <span class="block text-xs font-normal opacity-75">F2</span></button>
                    <button type="button" @click="setResult('rejected')"
                            :class="form.result === 'rejected' ? 'ring-4 ring-red-500/40 bg-red-600 text-white' : 'bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-300'"
                            class="rounded-xl px-4 py-5 text-lg font-semibold transition">Rechazado <span class="block text-xs font-normal opacity-75">F3</span></button>
                    <button type="button" @click="setResult('observed')"
                            :class="form.result === 'observed' ? 'ring-4 ring-amber-500/40 bg-amber-500 text-white' : 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'"
                            class="rounded-xl px-4 py-5 text-lg font-semibold transition">Observado <span class="block text-xs font-normal opacity-75">F4</span></button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div><label class="form-label" for="qc-grade">Calidad</label><input id="qc-grade" x-model="form.grade" class="form-input" maxlength="20" placeholder="Extra, I, II…"></div>
                    <div><label class="form-label" for="qc-caliber">Calibre</label><input id="qc-caliber" x-model="form.caliber" class="form-input" maxlength="20"></div>
                    <div><label class="form-label" for="qc-ripeness">Maduración</label><input id="qc-ripeness" x-model="form.ripeness" class="form-input" maxlength="30"></div>
                    <div><label class="form-label" for="qc-damage">% daños</label><input id="qc-damage" x-model="form.damage_pct" inputmode="decimal" class="form-input text-right"></div>
                    <div><label class="form-label" for="qc-bruise">% golpes</label><input id="qc-bruise" x-model="form.bruise_pct" inputmode="decimal" class="form-input text-right"></div>
                    <div><label class="form-label" for="qc-rot">% podredumbre</label><input id="qc-rot" x-model="form.rot_pct" inputmode="decimal" class="form-input text-right"></div>
                    <div><label class="form-label" for="qc-reject">% rechazo</label><input id="qc-reject" x-model="form.reject_pct" inputmode="decimal" class="form-input text-right"></div>
                    <div class="col-span-2"><label class="form-label" for="qc-at">Fecha y hora</label><input id="qc-at" type="datetime-local" x-model="form.controlled_at" class="form-input"></div>
                    <div class="col-span-2 md:col-span-3"><label class="form-label" for="qc-defects">Defectos</label><input id="qc-defects" x-model="form.defects" class="form-input" maxlength="2000"></div>
                    <div class="col-span-2 md:col-span-3"><label class="form-label" for="qc-notes">Observaciones</label><textarea id="qc-notes" x-model="form.notes" rows="2" class="form-input"></textarea></div>
                </div>

                <template x-if="target && target.type !== 'crate' && form.result !== 'observed'">
                    <label class="mt-5 flex items-start gap-3 rounded-lg border border-stone-200 p-3 text-sm dark:border-stone-700">
                        <input type="checkbox" x-model="form.apply_to_crates" class="mt-0.5 size-4 rounded border-stone-300 dark:border-stone-600 dark:bg-stone-900">
                        <span>
                            <span class="font-medium text-stone-800 dark:text-stone-200">Aplicar el resultado a todos los cajones elegibles</span>
                            <span class="block text-xs text-stone-500" x-text="'Cajones procesados o en control: ' + (target.eligible_crates ?? 0) + ' de ' + (target.total_crates ?? 0)"></span>
                        </span>
                    </label>
                </template>

                <template x-if="form.result === 'rejected'">
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50/50 p-4 dark:border-red-900 dark:bg-red-950/20">
                        <label class="flex items-center gap-3 text-sm font-medium text-stone-800 dark:text-stone-200">
                            <input type="checkbox" x-model="form.register_reject" class="size-4 rounded border-stone-300 dark:border-stone-600 dark:bg-stone-900">
                            Registrar rechazo (merma)
                        </label>
                        <div x-show="form.register_reject" class="mt-3 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="form-label" for="qc-reason">Motivo <span class="text-red-500">*</span></label>
                                <select id="qc-reason" x-ref="reason" x-model="form.reason_id" class="form-input">
                                    <option value="">Seleccionar…</option>
                                    @foreach ($reasons as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="!(target && target.type !== 'crate' && form.apply_to_crates)">
                                <label class="form-label" for="qc-weight">Peso rechazado (kg)</label>
                                <input id="qc-weight" x-model="form.reject_weight" inputmode="decimal" class="form-input text-right"
                                       :placeholder="target && target.weight ? 'Peso del cajón: ' + target.weight : ''">
                                <p class="form-hint" x-show="target && target.type === 'crate'">Vacío = peso del cajón.</p>
                            </div>
                            <p class="text-xs text-stone-500 md:col-span-2" x-show="target && target.type !== 'crate' && form.apply_to_crates">
                                Se registra un rechazo por cada cajón con su propio peso.
                            </p>
                        </div>
                    </div>
                </template>
            </x-panel>
        </div>

        {{-- 3. Confirmación --}}
        <div class="space-y-4">
            <x-panel title="3. Confirmar">
                <button type="button" @click="submit()" :disabled="saving || !target || !form.result"
                        class="btn btn-primary btn-lg w-full py-4 text-lg">
                    <x-icon name="check" class="size-5"/> <span x-text="saving ? 'Guardando…' : 'Registrar control (Ctrl+Enter)'"></span>
                </button>
                <template x-if="message">
                    <div class="mt-4 rounded-lg px-4 py-3 text-sm" role="status"
                         :class="messageType === 'error'
                            ? 'border border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'
                            : 'border border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'">
                        <p x-text="message"></p>
                        <a x-show="lastUrl" :href="lastUrl" class="link mt-1 inline-block text-xs">Ver control</a>
                    </div>
                </template>
            </x-panel>

            <x-panel title="Últimos registrados">
                <ul class="space-y-2 text-sm">
                    <template x-for="item in history" :key="item.id">
                        <li class="flex items-center justify-between gap-2">
                            <span><span class="code" x-text="item.code"></span> <span class="text-xs text-stone-500" x-text="item.time"></span></span>
                            <span class="text-xs font-medium" :class="{'text-emerald-600 dark:text-emerald-400': item.result === 'approved', 'text-red-600 dark:text-red-400': item.result === 'rejected', 'text-amber-600 dark:text-amber-400': item.result === 'observed'}" x-text="item.label"></span>
                        </li>
                    </template>
                    <li x-show="history.length === 0" class="text-stone-500">Sin registros en esta sesión.</li>
                </ul>
            </x-panel>
        </div>
    </div>

    @push('scripts')
        <script>
            function qualityScan(config) {
                const labels = { approved: 'Aprobado', rejected: 'Rechazado', observed: 'Observado' };
                const now = () => {
                    const d = new Date();
                    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                    return d.toISOString().slice(0, 16);
                };
                const emptyForm = () => ({
                    result: '', grade: '', caliber: '', ripeness: '', damage_pct: '', bruise_pct: '', rot_pct: '', reject_pct: '',
                    defects: '', notes: '', controlled_at: now(), register_reject: true, reason_id: '', reject_weight: '', apply_to_crates: false,
                });

                return {
                    code: config.initialCode || '',
                    target: null,
                    form: emptyForm(),
                    loading: false,
                    saving: false,
                    message: '',
                    messageType: 'success',
                    lastUrl: null,
                    history: [],
                    init() {
                        window.addEventListener('keydown', (e) => {
                            if (e.key === 'F2') { e.preventDefault(); this.setResult('approved'); }
                            if (e.key === 'F3') { e.preventDefault(); this.setResult('rejected'); }
                            if (e.key === 'F4') { e.preventDefault(); this.setResult('observed'); }
                            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); this.submit(); }
                        });
                        if (this.code) this.lookup();
                        this.$nextTick(() => this.$refs.code.focus());
                    },
                    setResult(result) {
                        this.form.result = result;
                        // Rechazado: el motivo es obligatorio, el cursor va directo ahí (se muestra en el próximo ciclo).
                        if (result === 'rejected' && this.form.register_reject) {
                            setTimeout(() => this.$refs.reason && this.$refs.reason.focus(), 30);
                        }
                    },
                    notify(type, text) {
                        this.messageType = type;
                        this.message = text;
                        if (window.sounds) (type === 'error' ? window.sounds.error?.() : window.sounds.success?.());
                    },
                    async lookup() {
                        const code = this.code.trim();
                        if (!code) return;
                        this.loading = true;
                        this.lastUrl = null;
                        try {
                            this.target = await window.api(config.lookupUrl + '?code=' + encodeURIComponent(code));
                            this.message = '';
                            this.form.apply_to_crates = false;
                        } catch (e) {
                            this.target = null;
                            this.notify('error', e.message);
                            this.$refs.code.select();
                        } finally {
                            this.loading = false;
                        }
                    },
                    async submit() {
                        if (this.saving || !this.target || !this.form.result) return;
                        this.saving = true;
                        const body = Object.assign({}, this.form, { target_type: this.target.type, target_id: this.target.id });
                        if (body.result !== 'rejected') { body.register_reject = false; body.reason_id = null; body.reject_weight = null; }
                        try {
                            const res = await window.api(config.storeUrl, { method: 'POST', body });
                            this.notify('success', res.message);
                            this.lastUrl = res.url;
                            this.history.unshift({ id: res.control_id, code: this.target.code, result: body.result, label: labels[body.result], time: new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) });
                            this.history = this.history.slice(0, 15);
                            const keep = { register_reject: this.form.register_reject, reason_id: this.form.reason_id };
                            this.form = Object.assign(emptyForm(), keep);
                            this.target = null;
                            this.code = '';
                        } catch (e) {
                            const first = e.errors && Object.values(e.errors)[0];
                            this.notify('error', first ? first[0] : e.message);
                        } finally {
                            this.saving = false;
                            this.$nextTick(() => this.$refs.code.focus());
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
