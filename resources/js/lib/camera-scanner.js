/**
 * Lector de códigos con la cámara del celular/tablet (códigos de barras y QR).
 *
 * - Usa el detector nativo del navegador (BarcodeDetector) si existe y, si no, ZXing
 *   (incluido en el sistema: no depende de internet).
 * - Al leer: suena, vibra, completa el campo y dispara Enter, igual que un lector USB.
 * - Modo continuo: la cámara queda abierta para leer un código tras otro (armado de cargas).
 *
 * Uso: window.scanInto(document.getElementById('campo'), { continuous: false, title: 'Cajón' })
 */

const FORMATS = ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'qr_code', 'itf', 'data_matrix'];
const REPEAT_MS = 2500; // el mismo código leído de nuevo dentro de este tiempo se ignora

let session = null;

function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => (k === 'text' ? (node.textContent = v) : node.setAttribute(k, v)));
    children.forEach((c) => node.appendChild(c));
    return node;
}

function buildOverlay(title, continuous) {
    const video = el('video', { playsinline: '', muted: '', autoplay: '', class: 'h-full w-full object-cover' });
    const status = el('p', { class: 'text-sm text-white/80', text: 'Apuntá la cámara al código de barras o QR' });
    const last = el('p', { class: 'min-h-7 font-mono text-lg font-bold text-emerald-300', 'aria-live': 'polite' });
    const close = el('button', { type: 'button', class: 'rounded-lg bg-white/15 px-5 py-3 text-base font-semibold text-white hover:bg-white/25', text: continuous ? 'Terminar' : 'Cancelar' });
    const torch = el('button', { type: 'button', class: 'hidden rounded-lg bg-white/15 px-4 py-3 text-base font-semibold text-white hover:bg-white/25', text: 'Linterna' });
    const frame = el('div', { class: 'pointer-events-none absolute inset-x-8 top-1/2 h-40 -translate-y-1/2 rounded-xl border-4 border-emerald-400/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]' });
    // Alternativa sin https: la cámara del celular saca una foto y el código se lee de la imagen.
    const photoInput = el('input', { type: 'file', accept: 'image/*', capture: 'environment', class: 'hidden', 'data-scan-photo': '' });
    const photo = el('label', { class: 'cursor-pointer rounded-lg bg-emerald-600 px-5 py-3 text-base font-semibold text-white hover:bg-emerald-500' }, [
        document.createTextNode('Sacar foto del código'), photoInput,
    ]);

    const overlay = el('div', { class: 'fixed inset-0 z-[70] flex flex-col bg-black', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Escanear con la cámara' }, [
        el('div', { class: 'flex items-center justify-between gap-3 px-4 py-3' }, [
            el('div', {}, [el('p', { class: 'text-base font-semibold text-white', text: title }), status]),
            el('div', { class: 'flex gap-2' }, [torch, close]),
        ]),
        el('div', { class: 'relative flex-1 overflow-hidden' }, [video, frame]),
        el('div', { class: 'flex flex-col items-center gap-3 px-4 py-4 text-center' }, [last, photo]),
    ]);

    return { overlay, video, status, last, close, torch, photoInput };
}

async function startNative(video, onCode) {
    const supported = await window.BarcodeDetector.getSupportedFormats();
    const detector = new window.BarcodeDetector({ formats: FORMATS.filter((f) => supported.includes(f)) });
    let running = true;
    const tick = async () => {
        if (!running) return;
        try {
            if (video.readyState >= 2) {
                const codes = await detector.detect(video);
                if (codes.length) onCode(codes[0].rawValue);
            }
        } catch (e) { /* cuadro sin código */ }
        setTimeout(tick, 120);
    };
    tick();
    return () => { running = false; };
}

async function startZxing(video, onCode) {
    const { BrowserMultiFormatReader } = await import('@zxing/browser');
    const reader = new BrowserMultiFormatReader(undefined, { delayBetweenScanAttempts: 120 });
    const controls = await reader.decodeFromVideoElement(video, (result) => {
        if (result) onCode(result.getText());
    });
    return () => controls.stop();
}

/** Lee un código de una foto (archivo de imagen). Devuelve el texto o null. */
export async function decodeImageFile(file) {
    const url = URL.createObjectURL(file);
    try {
        if ('BarcodeDetector' in window) {
            try {
                const supported = await window.BarcodeDetector.getSupportedFormats();
                const detector = new window.BarcodeDetector({ formats: FORMATS.filter((f) => supported.includes(f)) });
                const bitmap = await createImageBitmap(file);
                const codes = await detector.detect(bitmap);
                if (codes.length) return codes[0].rawValue;
            } catch (e) { /* se intenta con ZXing */ }
        }
        const { BrowserMultiFormatReader } = await import('@zxing/browser');
        const result = await new BrowserMultiFormatReader().decodeFromImageUrl(url);
        return result ? result.getText() : null;
    } catch (e) {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

export function openCameraScanner({ title = 'Escanear código', continuous = false, onCode }) {
    if (session) return;

    const ui = buildOverlay(title, continuous);
    document.body.appendChild(ui.overlay);
    document.body.classList.add('overflow-hidden');

    let stream = null;
    let stopDecoder = null;
    let lastCode = '';
    let lastAt = 0;
    let count = 0;

    const finish = () => {
        if (stopDecoder) stopDecoder();
        if (stream) stream.getTracks().forEach((t) => t.stop());
        ui.overlay.remove();
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', onKey);
        session = null;
    };
    const onKey = (e) => { if (e.key === 'Escape') finish(); };
    document.addEventListener('keydown', onKey);
    ui.close.addEventListener('click', finish);

    const handle = (raw) => {
        const code = String(raw || '').trim();
        const now = Date.now();
        if (!code || (code === lastCode && now - lastAt < REPEAT_MS)) return;
        lastCode = code;
        lastAt = now;
        count++;
        if (navigator.vibrate) navigator.vibrate(80);
        window.sounds?.success?.();
        ui.last.textContent = continuous ? `✔ ${code}  ·  ${count} leído${count === 1 ? '' : 's'}` : `✔ ${code}`;
        onCode(code);
        if (!continuous) setTimeout(finish, 250);
    };

    session = { finish };

    ui.photoInput.addEventListener('change', async () => {
        const file = ui.photoInput.files && ui.photoInput.files[0];
        ui.photoInput.value = '';
        if (!file) return;
        ui.status.textContent = 'Leyendo la foto…';
        ui.status.className = 'text-sm text-white/80';
        const code = await decodeImageFile(file);
        if (code) {
            ui.status.textContent = continuous ? 'Podés sacar otra foto o terminar.' : 'Código leído.';
            handle(code);
        } else {
            ui.status.textContent = 'No se encontró un código en la foto. Acercate, con buena luz y el código derecho, y probá de nuevo.';
            ui.status.className = 'text-sm font-semibold text-amber-300';
            window.sounds?.error?.();
        }
    });

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        // En http (red local sin certificado) el navegador no permite la cámara en vivo: se usa la foto.
        ui.status.textContent = 'Tocá «Sacar foto del código». (La cámara en vivo necesita https: ver docs/INSTALACION.md).';
        ui.status.className = 'text-sm font-semibold text-amber-300';
        return;
    }

    (async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            ui.video.srcObject = stream;
            await ui.video.play();

            const track = stream.getVideoTracks()[0];
            if (track?.getCapabilities?.().torch) {
                let on = false;
                ui.torch.classList.remove('hidden');
                ui.torch.addEventListener('click', () => {
                    on = !on;
                    track.applyConstraints({ advanced: [{ torch: on }] }).catch(() => {});
                });
            }

            stopDecoder = 'BarcodeDetector' in window
                ? await startNative(ui.video, handle).catch(() => startZxing(ui.video, handle))
                : await startZxing(ui.video, handle);
        } catch (e) {
            const denied = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError');
            ui.status.textContent = denied
                ? 'Permiso de cámara denegado. Habilitalo en el navegador o usá «Sacar foto del código».'
                : 'No se pudo abrir la cámara en vivo (' + (e?.message || e) + '). Podés usar «Sacar foto del código».';
            ui.status.className = 'text-sm font-semibold text-red-300';
        }
    })();
}

/**
 * Escanea con la cámara y escribe el resultado en el campo, disparando Enter como un lector USB:
 * así cada pantalla sigue su flujo normal (validar, avanzar al siguiente campo, guardar).
 */
export function scanInto(input, { continuous = false, title } = {}) {
    if (!input) return;
    openCameraScanner({
        title: title || input.closest('[data-scan-title]')?.dataset.scanTitle || input.getAttribute('aria-label') || input.placeholder || 'Escanear código',
        continuous,
        onCode: (code) => {
            input.focus();
            input.value = code;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true, cancelable: true }));
        },
    });
}
