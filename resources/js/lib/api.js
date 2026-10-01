/**
 * Cliente fetch con CSRF, JSON y manejo uniforme de errores.
 * Nunca asume que una operación se guardó si no hubo respuesta del servidor.
 */
export function newIdempotencyKey() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2);
}

export async function api(url, { method = 'GET', body = null, headers = {}, timeout = 15000 } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeout);
    const token = document.querySelector('meta[name=csrf-token]')?.content;

    let response;
    try {
        response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                ...headers,
            },
            body: body ? JSON.stringify(body) : null,
            signal: controller.signal,
            credentials: 'same-origin',
        });
    } catch (error) {
        clearTimeout(timer);
        window.dispatchEvent(new CustomEvent('connection-lost'));
        const err = new Error('Sin conexión con el servidor. La operación NO fue confirmada.');
        err.network = true;
        throw err;
    }
    clearTimeout(timer);

    let data = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (!response.ok) {
        if (response.status === 419) {
            const err = new Error('La sesión expiró. Recargá la página.');
            err.status = 419;
            throw err;
        }
        const message = (data && data.message) || 'No se pudo completar la operación.';
        const err = new Error(data && data.code ? message + ' Código: ' + data.code : message);
        err.status = response.status;
        err.data = data;
        err.errors = (data && data.errors) || {};
        throw err;
    }

    return data;
}
