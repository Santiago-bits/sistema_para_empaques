/**
 * Sonidos de confirmación generados con WebAudio (sin archivos, funcionan offline).
 * La activación de cada sonido se configura en Configuración → Producción.
 */
let ctx = null;

function tone(freq, duration, type = 'sine', when = 0, gain = 0.25) {
    ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    amp.gain.setValueAtTime(gain, ctx.currentTime + when);
    amp.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + when + duration);
    osc.connect(amp).connect(ctx.destination);
    osc.start(ctx.currentTime + when);
    osc.stop(ctx.currentTime + when + duration);
}

export const sounds = {
    enabled: { success: true, error: true, duplicate: true },
    configure(flags) {
        Object.assign(this.enabled, flags || {});
    },
    success() {
        if (!this.enabled.success) return;
        try {
            tone(880, 0.12, 'sine');
            tone(1320, 0.15, 'sine', 0.1);
        } catch (e) {}
    },
    error() {
        if (!this.enabled.error) return;
        try {
            tone(220, 0.35, 'square', 0, 0.2);
        } catch (e) {}
    },
    duplicate() {
        if (!this.enabled.duplicate) return;
        try {
            tone(440, 0.12, 'triangle');
            tone(440, 0.12, 'triangle', 0.18);
            tone(440, 0.12, 'triangle', 0.36);
        } catch (e) {}
    },
};
