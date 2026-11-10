<x-layouts.app title="Importar una carpeta">
    <x-page-header title="Importar una carpeta de planillas" subtitle="Cada planilla va sola a su sección. Nada se guarda hasta que revises y confirmes." :back="route('imports.index')"/>

    <div class="grid gap-6 lg:grid-cols-3"
         x-data="folderImport({{ \Illuminate\Support\Js::from(['uploadUrl' => route('imports.folder.upload'), 'showUrl' => route('imports.folder.show', $group), 'group' => $group]) }})">
        <x-panel title="1. Elegir las planillas" class="lg:col-span-2">
            <div class="space-y-4">
                <x-select name="mode" label="Si un registro ya existe (mismo código, CUIT, DNI o patente)" :options="\App\Services\ImportService::MODES" value="upsert" x-model="mode"/>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="btn btn-primary cursor-pointer justify-center" :class="busy && 'pointer-events-none opacity-50'">
                        <x-icon name="folder" class="size-4"/> Elegir una carpeta
                        <input type="file" class="sr-only" webkitdirectory directory multiple @change="pick($event)" :disabled="busy">
                    </label>
                    <label class="btn btn-secondary cursor-pointer justify-center" :class="busy && 'pointer-events-none opacity-50'">
                        <x-icon name="upload" class="size-4"/> Elegir varias planillas
                        <input type="file" class="sr-only" accept=".xlsx,.csv,.txt" multiple @change="pick($event)" :disabled="busy">
                    </label>
                </div>

                <template x-if="items.length">
                    <div>
                        <p class="mb-2 text-sm text-stone-600 dark:text-stone-400">
                            <span x-text="done"></span> de <span x-text="items.length"></span> planilla(s) revisadas
                        </p>
                        <div class="h-2.5 overflow-hidden bg-stone-200 dark:bg-stone-800">
                            <div class="h-full bg-brand-600 transition-all" :style="`width: ${items.length ? Math.round(done * 100 / items.length) : 0}%`"></div>
                        </div>
                        <ul class="mt-4 divide-y divide-stone-200 text-sm dark:divide-stone-800">
                            <template x-for="item in items" :key="item.key">
                                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                                    <span class="min-w-0 truncate font-medium" x-text="item.name"></span>
                                    <span class="text-right">
                                        <span x-show="item.state === 'waiting'" class="text-stone-400">En espera…</span>
                                        <span x-show="item.state === 'sending'" class="text-stone-500">Leyendo…</span>
                                        <span x-show="item.state === 'ok'" class="text-emerald-700 dark:text-emerald-400">→ <strong x-text="item.section"></strong></span>
                                        <span x-show="item.state === 'unknown'" class="text-amber-700 dark:text-amber-400">Sin reconocer: elegís vos la sección</span>
                                        <span x-show="item.state === 'error'" class="text-red-600 dark:text-red-400" x-text="item.message"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="skipped.length">
                    <p class="text-xs text-stone-500 dark:text-stone-400">
                        Se ignoraron <span x-text="skipped.length"></span> archivo(s) que no son planillas (o son Excel viejo .xls: guardalos como .xlsx):
                        <span x-text="skipped.join(', ')"></span>.
                    </p>
                </template>

                <div x-show="finished" class="flex justify-end">
                    <a :href="showUrl" class="btn btn-primary"><x-icon name="check" class="size-4"/> 2. Revisar y confirmar</a>
                </div>
            </div>
        </x-panel>

        <x-panel title="Cómo se ordena cada planilla">
            <ol class="list-inside list-decimal space-y-2 text-sm text-stone-600 dark:text-stone-400">
                <li>El sistema mira el <strong>nombre del archivo</strong> (por ejemplo «camioneros.xlsx») y los <strong>títulos de las columnas</strong>.</li>
                <li>Sólo la manda a una sección si las dos cosas coinciden. Ante la duda (por ejemplo, columnas que sirven para productores y para propietarios) la deja <strong>sin reconocer</strong> y elegís vos.</li>
                <li>Cada fila se valida igual que en una importación suelta. Las que tienen errores no se cargan.</li>
                <li>Al confirmar se cargan primero transportistas, clientes y cuadrillas, y después camiones, camioneros, destinos y empleados.</li>
            </ol>
            <p class="mt-3 text-xs text-stone-500 dark:text-stone-400">De cada Excel se lee la primera hoja. Las planillas de DTV-e se importan desde Logística → DTV-e.</p>
        </x-panel>
    </div>

    @push('scripts')
        <script>
            window.folderImport = function (config) {
                return {
                    ...config,
                    mode: 'upsert',
                    items: [],
                    skipped: [],
                    done: 0,
                    busy: false,
                    finished: false,
                    async pick(event) {
                        const files = Array.from(event.target.files || []);
                        event.target.value = '';
                        for (const file of files) {
                            const name = file.webkitRelativePath || file.name;
                            const base = file.name;
                            // Archivos temporales de Excel («~$…»), ocultos y lo que no es planilla no se mandan.
                            if (base.startsWith('~$') || base.startsWith('.') || !/\.(xlsx|csv|txt)$/i.test(base)) {
                                this.skipped.push(base);
                                continue;
                            }
                            this.items.push({ key: this.items.length + ':' + name, name, file, state: 'waiting', section: null, message: null });
                        }
                        await this.send();
                    },
                    async send() {
                        if (this.busy) return;
                        this.busy = true;
                        const token = document.querySelector('meta[name=csrf-token]')?.content;
                        for (const item of this.items.filter((i) => i.state === 'waiting')) {
                            item.state = 'sending';
                            const body = new FormData();
                            body.append('group', this.group);
                            body.append('mode', this.mode);
                            body.append('file', item.file, item.file.name);
                            try {
                                const response = await fetch(this.uploadUrl, {
                                    method: 'POST', body, credentials: 'same-origin',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
                                });
                                const data = await response.json().catch(() => ({}));
                                if (!response.ok) {
                                    item.state = 'error';
                                    item.message = response.status === 419 ? 'La sesión expiró. Recargá la página.'
                                        : (data.errors ? Object.values(data.errors)[0][0] : (data.message || 'No se pudo leer.'));
                                } else {
                                    item.state = data.recognized ? 'ok' : 'unknown';
                                    item.section = data.section;
                                }
                            } catch (e) {
                                item.state = 'error';
                                item.message = 'Sin conexión con el servidor.';
                            }
                            this.done++;
                        }
                        this.busy = false;
                        this.finished = this.items.some((i) => i.state === 'ok' || i.state === 'unknown');
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
