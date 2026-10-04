/**
 * Monitorea la conexión con el servidor: si una PC del galpón pierde la red se
 * muestra "Sin conexión con el servidor" para que el operador no asuma que la
 * operación quedó guardada.
 */
const state = { online: true };

function setOnline(value) {
    if (state.online === value) return;
    state.online = value;
    window.dispatchEvent(new CustomEvent(value ? 'connection-restored' : 'connection-lost'));
}

// La dirección viene del layout (<meta name="heartbeat">): así funciona también si el sistema está en una
// subcarpeta (XAMPP: /sistema_para_empaques/public), donde «/api/heartbeat» daba 404 y avisaba «sin conexión».
const heartbeatUrl = () => {
    const content = document.querySelector('meta[name=heartbeat]')?.content || '';
    return content.startsWith('http') ? content : '/api/heartbeat';
};

async function ping() {
    try {
        const r = await fetch(heartbeatUrl(), { headers: { Accept: 'application/json' }, credentials: 'omit', cache: 'no-store' });
        setOnline(r.ok || r.status === 429);
    } catch (e) {
        setOnline(false);
    }
}

window.addEventListener('offline', () => setOnline(false));
window.addEventListener('online', ping);
window.addEventListener('connection-lost', () => {
    if (state.online) {
        state.online = false;
        setTimeout(ping, 3000);
    }
});

if (document.querySelector('meta[name=heartbeat]')) {
    setInterval(ping, 15000);
}

window.connectionState = state;
window.checkConnection = ping;
