import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { api, newIdempotencyKey } from './lib/api';
import { sounds } from './lib/sounds';
import { theme } from './lib/theme';
import './lib/connection';
import { openCameraScanner, scanInto } from './lib/camera-scanner';
import { initShortcuts } from './lib/shortcuts';

window.Alpine = Alpine;
window.Chart = Chart;
window.api = api;
window.newIdempotencyKey = newIdempotencyKey;
window.sounds = sounds;
window.theme = theme;
window.openCameraScanner = openCameraScanner;
window.scanInto = scanInto;

// Lectores USB/Bluetooth configurados con teclado en inglés sobre Windows en español: el guion de
// los códigos («CJ-000123») llega como apóstrofo. En los campos de escaneo se corrige al instante
// (fase de captura: antes de que Alpine lea el valor). Los códigos del sistema nunca llevan «'».
document.addEventListener('input', (e) => {
    const el = e.target;
    if (el instanceof HTMLInputElement && el.hasAttribute('data-scan') && el.value.includes("'")) {
        el.value = el.value.replace(/'/g, '-');
    }
}, true);

// Paleta para gráficos que funciona en claro y oscuro.
window.chartColors = ['#16a34a', '#f97316', '#0ea5e9', '#a855f7', '#eab308', '#ef4444', '#14b8a6', '#64748b', '#ec4899', '#84cc16'];

Chart.defaults.font.family = 'ui-sans-serif, system-ui, "Segoe UI", Roboto, Arial, sans-serif';
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.legend.labels.boxWidth = 12;

function syncChartTheme() {
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.color = dark ? '#a8a29e' : '#57534e';
    Chart.defaults.borderColor = dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
}
syncChartTheme();
window.addEventListener('theme-changed', syncChartTheme);

/**
 * Confirmación para operaciones críticas (anular, cerrar carga, facturar, restaurar).
 * Uso: <form x-data x-confirm="¿Anular el cajón?">
 */
Alpine.directive('confirm', (el, { expression }) => {
    el.addEventListener('submit', (event) => {
        if (!window.confirm(expression)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });
});

/** Evita el doble envío de formularios (doble clic o lector que envía Enter dos veces). */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.allowResubmit !== undefined || event.defaultPrevented) return;
    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    setTimeout(() => {
        form.querySelectorAll('button[type=submit]').forEach((b) => b.setAttribute('disabled', 'disabled'));
    }, 0);
    setTimeout(() => {
        form.dataset.submitting = '0';
        form.querySelectorAll('button[type=submit]').forEach((b) => b.removeAttribute('disabled'));
    }, 4000);
});

Alpine.start();

// Atajos de teclado: sólo en el layout principal (el kiosco y el login no los cargan).
if (window.galpon?.shortcuts) initShortcuts(window.galpon.shortcuts);
