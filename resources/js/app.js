import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { api, newIdempotencyKey } from './lib/api';
import { sounds } from './lib/sounds';
import { theme } from './lib/theme';
import './lib/connection';

window.Alpine = Alpine;
window.Chart = Chart;
window.api = api;
window.newIdempotencyKey = newIdempotencyKey;
window.sounds = sounds;
window.theme = theme;

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
