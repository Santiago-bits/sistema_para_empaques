/**
 * App instalable: registra el service worker (necesario para que Chrome/Edge ofrezcan «Instalar») y maneja el
 * botón «Instalar app» del encabezado. En iPhone no hay botón: se instala con Compartir → «Agregar a inicio».
 */
let deferredPrompt = null;

export function registerApp(Alpine) {
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        window.dispatchEvent(new CustomEvent('app-installable'));
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.dispatchEvent(new CustomEvent('app-installed'));
    });

    Alpine.data('installApp', () => ({
        available: deferredPrompt !== null,
        init() {
            window.addEventListener('app-installable', () => (this.available = true));
            window.addEventListener('app-installed', () => (this.available = false));
        },
        async install() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice.catch(() => null);
            deferredPrompt = null;
            this.available = false;
        },
    }));
}
