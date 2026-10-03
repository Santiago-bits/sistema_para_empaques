/*
 * App instalable del galpón. Este archivo sólo permite instalar el sistema como app y muestra una pantalla
 * clara si se corta la conexión. NO guarda pantallas ni datos en la PC (hay datos personales y de dinero):
 * todo se pide siempre al servidor.
 */
const OFFLINE = '/offline.html';
const CACHE = 'galpon-offline-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Sólo las pantallas (no imágenes, ni datos, ni formularios): si no hay conexión, aviso claro.
    if (event.request.mode !== 'navigate' || event.request.method !== 'GET') return;
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});
