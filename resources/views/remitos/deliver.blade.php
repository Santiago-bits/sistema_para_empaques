<x-layouts.app :title="'Entrega · '.$remito->number">
    <x-page-header :title="'Registrar entrega · Remito '.$remito->number" :subtitle="($remito->client?->business_name ?? '').' · '.($remito->destination?->name ?? '')" :back="route('remitos.show', $remito)"/>

    <form method="POST" action="{{ route('remitos.deliver', $remito) }}" class="space-y-6" x-data="signaturePad()" @submit="beforeSubmit($event)">
        @csrf
        <x-panel title="Receptor">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input name="receiver_name" label="Nombre y apellido" required/>
                <x-input name="receiver_dni" label="DNI" inputmode="numeric" required/>
                <x-input name="delivered_at" type="datetime-local" label="Fecha y hora" hint="Vacío = ahora."/>
            </div>
            <div class="mt-4"><x-textarea name="delivery_notes" label="Observaciones" rows="2"/></div>
        </x-panel>

        <x-panel title="Firma del receptor">
            <x-slot:actions><button type="button" class="btn btn-ghost btn-sm" @click="clear()">Borrar</button></x-slot:actions>
            <canvas x-ref="canvas" class="h-48 w-full touch-none rounded-lg border-2 border-dashed border-stone-300 bg-white dark:border-stone-600"
                    @pointerdown="start($event)" @pointermove="draw($event)" @pointerup="end()" @pointerleave="end()" aria-label="Área de firma"></canvas>
            <input type="hidden" name="signature" x-ref="signature">
            <p class="form-hint">Firmá con el dedo, lápiz o mouse. Preparado para firma digital en una versión futura.</p>
            @error('signature')<p class="form-error">{{ $message }}</p>@enderror
            <p x-show="error" x-text="error" class="form-error"></p>
        </x-panel>

        <div class="flex justify-end gap-2">
            <a href="{{ route('remitos.show', $remito) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary btn-lg">Confirmar entrega</button>
        </div>
    </form>

    @push('scripts')
        <script>
            window.signaturePad = function () {
                return {
                    ctx: null, drawing: false, dirty: false, error: '',
                    init() {
                        this.setup();
                        // Al girar la tablet cambia el tamaño: se reajusta el lienzo si todavía no se firmó.
                        window.addEventListener('resize', () => { if (!this.dirty) this.setup(); });
                    },
                    setup() {
                        const c = this.$refs.canvas;
                        const ratio = window.devicePixelRatio || 1;
                        c.width = c.offsetWidth * ratio;
                        c.height = c.offsetHeight * ratio;
                        this.ctx = c.getContext('2d');
                        this.ctx.scale(ratio, ratio);
                        this.ctx.lineWidth = 2.2;
                        this.ctx.lineCap = 'round';
                        this.ctx.strokeStyle = '#111';
                    },
                    pos(e) { const r = this.$refs.canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; },
                    start(e) { this.drawing = true; const [x, y] = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); },
                    draw(e) { if (!this.drawing) return; const [x, y] = this.pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); this.dirty = true; this.error = ''; },
                    end() { this.drawing = false; },
                    clear() { this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height); this.dirty = false; },
                    beforeSubmit(e) {
                        if (!this.dirty) { e.preventDefault(); this.error = 'Falta la firma del receptor.'; return; }
                        this.$refs.signature.value = this.$refs.canvas.toDataURL('image/png');
                    },
                };
            };
        </script>
    @endpush
</x-layouts.app>
