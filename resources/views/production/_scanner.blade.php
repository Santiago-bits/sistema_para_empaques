{{--
    Pantalla de escaneo compartida por "Modo escaneo" (con menú) y "Kiosco" (sin menú).
    Flujo 100% teclado: CAJÓN ⏎ → EMBALADOR ⏎ → PESO ⏎ → (variedad/tamaño recordados) → registra.
    F2 = volver a CAJÓN · Esc = limpiar · cada envío lleva una clave idempotente que se reutiliza en
    los reintentos, así un corte de red nunca genera registros duplicados.
--}}
@php
    $big = $kiosk ?? false;
    $label = 'block text-sm font-bold tracking-widest uppercase '.($big ? 'text-stone-400' : 'text-stone-500 dark:text-stone-400');
    $field = 'block w-full rounded-xl border-2 bg-white px-4 font-mono font-semibold tracking-wide text-stone-900 outline-none transition '
        .'focus:border-brand-500 focus:ring-4 focus:ring-brand-500/30 dark:bg-stone-950 dark:text-white '
        .($big ? 'py-4 text-3xl' : 'py-3 text-2xl');
@endphp
<div x-data="scanner({{ \Illuminate\Support\Js::from($config) }})" x-init="init()" @keydown.window="globalKeys($event)"
     class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]" :class="flashClass">

    {{-- Formulario de escaneo --}}
    <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-7 dark:border-stone-800 dark:bg-stone-900">
        {{-- Mensaje de estado grande --}}
        <div class="mb-6 flex min-h-16 items-center gap-3 rounded-xl px-4 py-3 text-lg font-semibold"
             :class="{
                'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-200': status === 'ok',
                'bg-red-100 text-red-900 dark:bg-red-500/20 dark:text-red-200': status === 'error',
                'bg-orange-100 text-orange-900 dark:bg-orange-500/20 dark:text-orange-200': status === 'duplicate' || status === 'auth',
                'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300': status === 'idle' || status === 'busy',
             }" role="status" aria-live="assertive">
            <span x-show="status === 'busy'" class="size-5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
            <span x-text="message"></span>
            <button type="button" x-show="pendingRetry" @click="submit()" class="btn btn-warning ml-auto">Reintentar</button>
        </div>

        <form @submit.prevent="submit()" class="grid gap-5 md:grid-cols-2" autocomplete="off" data-allow-resubmit>
            <div class="md:col-span-2">
                <label for="scan-crate" class="{{ $label }}">Cajón</label>
                <div class="relative mt-1">
                    <input id="scan-crate" x-ref="crate" x-model.trim="form.crate_code" @keydown.enter.prevent="afterCrate()"
                           class="{{ $field }}" :class="crateState === 'error' ? 'border-red-500' : (crateState === 'ok' ? 'border-emerald-500' : 'border-stone-300 dark:border-stone-700')"
                           placeholder="Escanear código del cajón" inputmode="text" spellcheck="false">
                    <span x-show="crateInfo" x-text="crateInfo" class="absolute top-1/2 right-4 -translate-y-1/2 text-sm font-medium text-stone-500"></span>
                </div>
            </div>

            <div class="md:col-span-2">
                <div class="flex items-center justify-between">
                    <label for="scan-packer" class="{{ $label }}">Embalador</label>
                    <label class="flex items-center gap-1.5 text-xs text-stone-500"><input type="checkbox" x-model="pin.packer" class="rounded"> Fijar</label>
                </div>
                <div class="relative mt-1">
                    <input id="scan-packer" x-ref="packer" x-model.trim="form.packer_code" @keydown.enter.prevent="afterPacker()"
                           class="{{ $field }}" :class="packerState === 'error' ? 'border-red-500' : (packerState === 'ok' ? 'border-emerald-500' : 'border-stone-300 dark:border-stone-700')"
                           placeholder="Escanear credencial (EMB…)" spellcheck="false">
                    <span x-show="packerName" x-text="packerName" class="absolute top-1/2 right-4 -translate-y-1/2 text-sm font-medium text-stone-500"></span>
                </div>
            </div>

            <div>
                <label for="scan-weight" class="{{ $label }}">Peso (kg)</label>
                <div class="mt-1 flex gap-2">
                    <input id="scan-weight" x-ref="weight" x-model="form.weight" @keydown.enter.prevent="afterWeight()" @input="onWeightInput($event)"
                           class="{{ $field }} border-stone-300 dark:border-stone-700" inputmode="decimal" placeholder="0,00">
                    <button type="button" x-show="config.scaleDriver !== 'manual'" @click="readScale()" class="btn btn-secondary shrink-0" title="Leer balanza">
                        <x-icon name="scale" class="size-6"/>
                    </button>
                </div>
                <p class="mt-1 text-xs text-stone-500">Rango permitido: <span x-text="fmt(config.weightMin)"></span> a <span x-text="fmt(config.weightMax)"></span> kg</p>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="scan-variety" class="{{ $label }}">Variedad</label>
                    <label class="flex items-center gap-1.5 text-xs text-stone-500"><input type="checkbox" x-model="pin.variety" class="rounded"> Fijar</label>
                </div>
                <select id="scan-variety" x-ref="variety" x-model="form.variety_id" @keydown.enter.prevent="afterSelect('variety')"
                        class="{{ $field }} mt-1 border-stone-300 font-sans dark:border-stone-700">
                    <option value="">—</option>
                    @foreach ($varieties as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="scan-size" class="{{ $label }}">Tamaño</label>
                    <label class="flex items-center gap-1.5 text-xs text-stone-500"><input type="checkbox" x-model="pin.size" class="rounded"> Fijar</label>
                </div>
                <select id="scan-size" x-ref="size" x-model="form.size_id" @keydown.enter.prevent="afterSelect('size')"
                        class="{{ $field }} mt-1 border-stone-300 font-sans dark:border-stone-700">
                    <option value="">—</option>
                    @foreach ($sizes as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="scan-line" class="{{ $label }}">Línea</label>
                    <select id="scan-line" x-model="form.production_line_id" class="form-input mt-1 py-3">
                        <option value="">—</option>
                        @foreach ($lines as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="scan-lot" class="{{ $label }}">Lote @if ($config['requireLot'])<span class="text-red-500">*</span>@endif</label>
                    <select id="scan-lot" x-model="form.lot_id" class="form-input mt-1 py-3">
                        <option value="">{{ $config['requireLot'] ? 'Seleccionar…' : 'Del cajón' }}</option>
                        @foreach ($lots as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="btn btn-primary w-full rounded-xl py-5 text-2xl font-bold tracking-wide uppercase" :disabled="status === 'busy'">
                    Registrar
                </button>
                <p class="mt-2 text-center text-xs text-stone-500">⏎ avanza · <kbd>F2</kbd> volver al cajón · <kbd>Esc</kbd> limpiar</p>
            </div>
        </form>
    </section>

    {{-- Contadores y últimos registros --}}
    <aside class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs font-semibold tracking-wider text-stone-500 uppercase">Cajones del turno</p>
                <p class="mt-1 text-4xl font-bold tabular-nums" x-text="totals.crates"></p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900">
                <p class="text-xs font-semibold tracking-wider text-stone-500 uppercase">Kg del turno</p>
                <p class="mt-1 text-4xl font-bold tabular-nums" x-text="fmt(totals.kg, 1)"></p>
            </div>
        </div>
        <p class="text-xs text-stone-500" x-show="config.shift">Turno: <span x-text="config.shift"></span></p>

        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <p class="border-b border-stone-200 px-4 py-2.5 text-sm font-semibold dark:border-stone-800">Últimos registros</p>
            <ul class="max-h-[60vh] divide-y divide-stone-100 overflow-y-auto dark:divide-stone-800">
                <template x-for="r in recent" :key="r.id">
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm" :class="r.voided && 'line-through opacity-50'">
                        <div class="min-w-0">
                            <p class="truncate font-mono font-semibold" x-text="r.crate"></p>
                            <p class="truncate text-xs text-stone-500" x-text="[r.packer, r.variety, r.size].filter(Boolean).join(' · ')"></p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold tabular-nums" x-text="fmt(r.weight) + ' kg'"></p>
                            <p class="text-xs text-stone-500 tabular-nums" x-text="r.time"></p>
                        </div>
                    </li>
                </template>
                <li x-show="recent.length === 0" class="px-4 py-8 text-center text-sm text-stone-500">Sin registros en este turno.</li>
            </ul>
        </div>
    </aside>

    {{-- Autorización de supervisor (peso fuera de rango) --}}
    <div x-show="auth.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
        <form @submit.prevent="submitAuthorization()" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl dark:bg-stone-900">
            <div>
                <h2 class="text-lg font-bold text-orange-600">Autorización de supervisor</h2>
                <p class="mt-1 text-sm text-stone-600 dark:text-stone-400" x-text="auth.message"></p>
            </div>
            <div>
                <label class="form-label" for="auth-login">Usuario del supervisor</label>
                <input id="auth-login" x-ref="authLogin" x-model="auth.login" class="form-input" autocomplete="off" required>
            </div>
            <div>
                <label class="form-label" for="auth-password">Contraseña</label>
                <input id="auth-password" type="password" x-model="auth.password" class="form-input" autocomplete="off" required>
            </div>
            <div>
                <label class="form-label" for="auth-reason">Motivo</label>
                <input id="auth-reason" x-model="auth.reason" class="form-input" maxlength="255" required placeholder="Ej.: cajón de mayor tamaño">
            </div>
            <p x-show="auth.error" x-text="auth.error" class="text-sm text-red-600"></p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="cancelAuthorization()">Cancelar</button>
                <button class="btn btn-warning">Autorizar y registrar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
window.scanner = function (config) {
    const STORAGE = 'galpon.scanner.' + config.userId;
    const blank = () => ({
        crate_code: '', packer_code: '', weight: '', variety_id: '', size_id: '', production_line_id: '', lot_id: '',
        weight_source: 'manual',
    });

    return {
        config,
        form: blank(),
        pin: { packer: true, variety: true, size: true },
        totals: config.totals,
        recent: config.recent,
        status: 'idle',
        message: 'Listo para escanear.',
        flashClass: '',
        crateState: '', crateInfo: '',
        packerState: '', packerName: '',
        pendingKey: null,
        pendingRetry: false,
        auth: { open: false, login: '', password: '', reason: '', message: '', error: '' },

        init() {
            window.sounds.configure((window.galpon || {}).sounds);
            try {
                const saved = JSON.parse(localStorage.getItem(STORAGE) || '{}');
                Object.assign(this.pin, saved.pin || {});
                ['packer_code', 'variety_id', 'size_id', 'production_line_id'].forEach(k => {
                    if (saved[k] !== undefined) this.form[k] = String(saved[k] ?? '');
                });
            } catch (e) {}
            if (this.form.packer_code) this.lookupPacker();
            this.$watch('form', () => this.persist(), { deep: true });
            this.$watch('pin', () => this.persist(), { deep: true });
            window.addEventListener('connection-lost', () => {
                if (this.status === 'busy') this.networkError();
            });
            this.$nextTick(() => this.focus('crate'));
        },

        persist() {
            try {
                localStorage.setItem(STORAGE, JSON.stringify({
                    pin: this.pin,
                    packer_code: this.pin.packer ? this.form.packer_code : '',
                    variety_id: this.pin.variety ? this.form.variety_id : '',
                    size_id: this.pin.size ? this.form.size_id : '',
                    production_line_id: this.form.production_line_id,
                }));
            } catch (e) {}
        },

        fmt(n, d = 2) {
            return Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d });
        },

        // Foco síncrono: los lectores envían caracteres en milisegundos y no pueden esperar al próximo ciclo.
        focus(ref) {
            const el = this.$refs[ref];
            if (!el) return;
            el.focus();
            if (el.select) el.select();
        },

        flash(kind) {
            this.flashClass = '';
            this.$nextTick(() => { this.flashClass = 'flash-' + kind; });
        },

        say(status, message) {
            this.status = status;
            this.message = message;
        },

        globalKeys(e) {
            // Con el pedido de autorización abierto, ninguna tecla puede ir a los campos de atrás
            // (evita, por ejemplo, que la contraseña del supervisor quede escrita en el campo peso).
            if (this.auth.open && !e.target.closest('[role=dialog]')) {
                if (e.key === 'Enter') e.preventDefault();
                this.$refs.authLogin?.focus();
                return;
            }
            if (e.key === 'F2') { e.preventDefault(); this.focus('crate'); }
            if (e.key === 'Escape' && !this.auth.open) { e.preventDefault(); this.reset(true); }
        },

        reset(full = false) {
            this.form.crate_code = '';
            this.form.weight = '';
            this.form.weight_source = 'manual';
            this.crateState = ''; this.crateInfo = '';
            if (full || !this.pin.packer) { this.form.packer_code = ''; this.packerState = ''; this.packerName = ''; }
            if (full || !this.pin.variety) this.form.variety_id = '';
            if (full || !this.pin.size) this.form.size_id = '';
            this.pendingKey = null;
            this.pendingRetry = false;
            if (full) this.say('idle', 'Listo para escanear.');
            this.focus('crate');
        },

        // El foco avanza en el acto: un lector rápido nunca escribe en el campo equivocado.
        // La validación corre en segundo plano y, si falla, devuelve el foco con el error.
        afterCrate() {
            const code = this.form.crate_code;
            if (!code) return;
            this.nextAfterCrate();
            this.validateCrate(code);
        },

        async validateCrate(code) {
            try {
                const data = await window.api(this.config.lookupUrl + '?crate=' + encodeURIComponent(code));
                if (this.form.crate_code !== code) return; // ya se registró o cambió
                const c = data.crate;
                if (c && !c.ok) {
                    this.crateState = 'error';
                    const dup = c.reason === 'duplicate';
                    this.say(dup ? 'duplicate' : 'error', c.message);
                    this.flash(dup ? 'warn' : 'error');
                    dup ? window.sounds.duplicate() : window.sounds.error();
                    this.form.crate_code = '';
                    this.focus('crate');
                    return;
                }
                this.crateState = 'ok';
                this.crateInfo = c && c.exists ? (c.lot ? 'Lote ' + c.lot : '') : 'Nuevo';
            } catch (e) {
                // Sin validación previa: el servidor valida igual al registrar.
            }
        },

        nextAfterCrate() {
            if (this.form.packer_code && this.packerState === 'ok') return this.focus('weight');
            this.focus('packer');
        },

        async lookupPacker() {
            const code = this.form.packer_code;
            if (!code) return false;
            try {
                const data = await window.api(this.config.lookupUrl + '?packer=' + encodeURIComponent(code));
                const p = data.packer;
                if (this.form.packer_code !== code) return true;
                this.packerState = p && p.ok ? 'ok' : 'error';
                this.packerName = p && p.ok ? p.name : '';
                if (p && p.ok) this.form.packer_code = p.code;
                return p && p.ok ? true : (p ? p.message : 'Embalador no válido');
            } catch (e) {
                return true;
            }
        },

        afterPacker() {
            if (!this.form.packer_code) return;
            this.focus('weight');
            this.lookupPacker().then((result) => {
                if (result === true) return;
                this.say('error', result);
                this.flash('error');
                window.sounds.error();
                this.form.packer_code = '';
                this.focus('packer');
            });
        },

        // Si llega una letra al campo peso es una credencial escaneada (embalador fijado): se redirige.
        onWeightInput(e) {
            const raw = e.target.value;
            if (/[A-Za-z]/.test(raw)) {
                const letters = raw.replace(/^[0-9.,]+/, '');
                this.form.weight = '';
                this.form.packer_code = letters.toUpperCase();
                this.packerState = '';
                this.packerName = '';
                this.focus('packer');
                const el = this.$refs.packer;
                if (el) {
                    el.value = this.form.packer_code; // sin esperar al ciclo reactivo
                    el.setSelectionRange(el.value.length, el.value.length);
                }
                return;
            }
            this.form.weight = raw.replace(/[^0-9.,]/g, '');
            this.form.weight_source = 'manual';
        },

        afterWeight() {
            if (!this.form.weight) return;
            if (!this.form.variety_id) return this.focus('variety');
            if (!this.form.size_id) return this.focus('size');
            this.submit();
        },

        afterSelect(which) {
            if (which === 'variety' && !this.form.size_id) return this.focus('size');
            this.submit();
        },

        async readScale() {
            try {
                const data = await window.api(this.config.scaleUrl);
                if (data.reading && data.reading.weight) {
                    this.form.weight = String(data.reading.weight).replace('.', ',');
                    this.form.weight_source = 'scale';
                    this.say('idle', 'Peso leído de la balanza.');
                } else {
                    this.say('error', 'La balanza no informó un peso. Ingresalo manualmente.');
                }
            } catch (e) {
                this.say('error', 'No se pudo leer la balanza. Ingresá el peso manualmente.');
            }
            this.focus('weight');
        },

        payload(extra = {}) {
            return Object.assign({}, this.form, {
                weight: String(this.form.weight).replace(',', '.'),
                production_line_id: this.form.production_line_id || null,
                lot_id: this.form.lot_id || null,
                idempotency_key: this.pendingKey,
            }, extra);
        },

        async submit(extra = {}) {
            if (this.status === 'busy') return;
            const missing = !this.form.crate_code ? 'crate' : !this.form.packer_code ? 'packer'
                : !this.form.weight ? 'weight' : !this.form.variety_id ? 'variety' : !this.form.size_id ? 'size' : null;
            if (missing) {
                this.say('error', 'Completá: ' + { crate: 'cajón', packer: 'embalador', weight: 'peso', variety: 'variedad', size: 'tamaño' }[missing]);
                window.sounds.error();
                return this.focus(missing);
            }
            if (this.config.requireLot && !this.form.lot_id) {
                this.say('error', 'Seleccioná el lote.');
                window.sounds.error();
                return;
            }

            // La clave se genera una vez por operación y se reutiliza en los reintentos.
            this.pendingKey = this.pendingKey || window.newIdempotencyKey();
            this.say('busy', 'Registrando ' + this.form.crate_code + '…');
            this.pendingRetry = false;

            try {
                const data = await window.api(this.config.storeUrl, { method: 'POST', body: this.payload(extra) });
                this.success(data);
            } catch (e) {
                if (e.network) return this.networkError();
                const d = e.data || {};
                if (d.requires_authorization) return this.askAuthorization(d.message);
                if (this.auth.open && d.field === 'supervisor') {
                    this.auth.error = d.message;
                    this.say('auth', d.message);
                    return;
                }
                this.auth.open = false;
                const dup = d.reason === 'duplicate';
                const firstError = e.errors && Object.values(e.errors)[0];
                this.say(dup ? 'duplicate' : 'error', firstError ? firstError[0] : e.message);
                this.flash(dup ? 'warn' : 'error');
                dup ? window.sounds.duplicate() : window.sounds.error();
                // Error definitivo: la próxima operación usa una clave nueva.
                this.pendingKey = null;
                const field = d.field === 'packer' ? 'packer' : d.field === 'weight' ? 'weight' : 'crate';
                if (field === 'crate') this.form.crate_code = '';
                this.focus(field);
            }
        },

        success(data) {
            this.auth.open = false;
            this.auth.password = '';
            const r = data.record;
            this.recent.unshift(r);
            this.recent = this.recent.slice(0, 15);
            this.totals = data.totals;
            this.say('ok', (data.replay ? '✔ Ya estaba registrado: ' : '✔ Registrado ') + r.crate + ' · ' + this.fmt(r.weight) + ' kg · ' + (r.packer_name || r.packer));
            this.flash('ok');
            window.sounds.success();
            this.reset(false);
            this.say('ok', this.message);
        },

        networkError() {
            this.say('error', 'SIN CONEXIÓN CON EL SERVIDOR — el cajón ' + this.form.crate_code + ' NO se guardó. Revisá la red y tocá Reintentar.');
            this.flash('error');
            window.sounds.error();
            this.pendingRetry = true;
        },

        askAuthorization(message) {
            this.auth.open = true;
            this.auth.error = '';
            this.auth.message = message;
            this.say('auth', message);
            window.sounds.duplicate();
            // x-show muestra el modal en un setTimeout: se enfoca recién cuando ya es visible.
            const focusLogin = (tries = 0) => {
                const el = this.$refs.authLogin;
                if (el && el.offsetParent !== null) return el.focus();
                if (tries < 20) setTimeout(() => focusLogin(tries + 1), 15);
            };
            focusLogin();
        },

        submitAuthorization() {
            this.status = 'idle';
            this.submit({ supervisor_login: this.auth.login, supervisor_password: this.auth.password, authorization_reason: this.auth.reason });
        },

        cancelAuthorization() {
            this.auth = { open: false, login: '', password: '', reason: '', message: '', error: '' };
            this.pendingKey = null;
            this.say('idle', 'Registro cancelado. Corregí el peso.');
            this.focus('weight');
        },
    };
};
</script>
@endpush
