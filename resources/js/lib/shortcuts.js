/**
 * Atajos de teclado globales (tipo Excel). La lista visible está en config/shortcuts.php y en
 * «Ayuda y atajos»; acá sólo el comportamiento.
 *
 * - Las teclas de una sola letra no actúan mientras se escribe en un campo.
 * - Una pantalla puede manejar ella misma algunas teclas declarándolas en un contenedor:
 *   <div data-local-keys="F2 F3 Escape Ctrl+Enter"> (modo escaneo, control de calidad).
 * - Las URLs llegan del servidor ya filtradas por permisos (window.galpon.shortcuts).
 */

const GO_TIMEOUT_MS = 1500;
const FOCUS_TABLE_KEY = 'galpon.focusTable';

const isTyping = (el) => !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

function visible(el) {
    return !!el && el.getClientRects().length > 0;
}

function comboOf(e) {
    const parts = [];
    if (e.ctrlKey || e.metaKey) parts.push('Ctrl');
    if (e.altKey) parts.push('Alt');
    // Con letras, Shift sólo cuenta si va con Ctrl/Alt (Ctrl+Shift+E); solo, ya cambia la tecla («?»).
    if (e.shiftKey && (e.key.length > 1 || e.ctrlKey || e.altKey || e.metaKey)) parts.push('Shift');
    parts.push(e.key.length === 1 ? e.key.toUpperCase() : e.key);
    return parts.join('+');
}

function localKeys() {
    const keys = new Set();
    document.querySelectorAll('[data-local-keys]').forEach((el) => {
        el.dataset.localKeys.split(/\s+/).filter(Boolean).forEach((k) => keys.add(k));
    });
    return keys;
}

let toastTimer = null;
function toast(message) {
    let el = document.getElementById('shortcut-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'shortcut-toast';
        el.setAttribute('role', 'status');
        el.className = 'fixed bottom-4 left-1/2 z-[70] -translate-x-1/2 rounded-lg bg-stone-900 px-4 py-2 text-sm text-white shadow-lg dark:bg-stone-100 dark:text-stone-900';
        document.body.appendChild(el);
    }
    el.textContent = message;
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, 2200);
}

function go(url) {
    if (url) window.location.assign(url);
}

const main = () => document.querySelector('main') || document.body;

function newButton() {
    return [...main().querySelectorAll('[data-shortcut="new"], a[href$="/nuevo"], a[href$="/nueva"], a[href$="/create"]')]
        .find((el) => visible(el));
}

function exportButton() {
    return [...main().querySelectorAll('a[href*="format=xlsx"]')].find((el) => visible(el))
        || [...main().querySelectorAll('a[href*="format=xlsx"]')][0];
}

/** Formulario a guardar: el del campo activo o, si hay uno solo de edición en la pantalla, ése. */
function formToSave() {
    const active = document.activeElement;
    if (active?.form && (active.form.method || '').toLowerCase() === 'post') return active.form;
    const forms = [...main().querySelectorAll('form')].filter((f) => (f.getAttribute('method') || 'get').toLowerCase() === 'post'
        && !f.hasAttribute('data-no-shortcut') && visible(f) && f.querySelector('input:not([type=hidden]), textarea, select'));
    return forms.length === 1 ? forms[0] : null;
}

function submit(form) {
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
}

/* ---------- Tablas: moverse por filas como en Excel ---------- */

function tableRows() {
    const table = [...main().querySelectorAll('table.table')].find((t) => visible(t));
    return table ? [...table.tBodies].flatMap((b) => [...b.rows]).filter((r) => r.cells.length > 1 && visible(r)) : [];
}

function prepareRows() {
    tableRows().forEach((row) => {
        if (!row.hasAttribute('tabindex')) row.setAttribute('tabindex', '-1');
    });
}

function focusRow(row) {
    if (!row) return;
    if (!row.hasAttribute('tabindex')) row.setAttribute('tabindex', '-1');
    row.focus({ preventScroll: true });
    row.scrollIntoView({ block: 'nearest' });
}

function rowLink(row) {
    return row.querySelector('a[href]:not([href^="#"]):not([target=_blank])');
}

function handleRowKeys(e, row) {
    const rows = tableRows();
    const i = rows.indexOf(row);
    if (i === -1) return false;
    switch (e.key) {
        case 'ArrowDown': focusRow(rows[Math.min(i + 1, rows.length - 1)]); return true;
        case 'ArrowUp': focusRow(rows[Math.max(i - 1, 0)]); return true;
        case 'Home': focusRow(rows[0]); return true;
        case 'End': focusRow(rows[rows.length - 1]); return true;
        case 'Enter': {
            const link = rowLink(row);
            if (link) link.click();
            return true;
        }
        case 'PageDown':
        case 'PageUp': {
            const rel = e.key === 'PageDown' ? 'next' : 'prev';
            const link = document.querySelector(`a[rel="${rel}"]`);
            if (link) {
                try { sessionStorage.setItem(FOCUS_TABLE_KEY, '1'); } catch { /* sin almacenamiento */ }
                link.click();
            } else {
                focusRow(e.key === 'PageDown' ? rows[rows.length - 1] : rows[0]);
            }
            return true;
        }
        case 'Escape': row.blur(); return true;
        default: return false;
    }
}

/* ---------- Teclado ---------- */

export function initShortcuts(config = {}) {
    const functions = config.functions || {};
    const goKeys = config.go || {};
    let pendingG = 0;

    prepareRows();
    document.addEventListener('alpine:initialized', prepareRows);

    // Al pasar de página con Av Pág / Re Pág se vuelve a la tabla.
    try {
        if (sessionStorage.getItem(FOCUS_TABLE_KEY)) {
            sessionStorage.removeItem(FOCUS_TABLE_KEY);
            requestAnimationFrame(() => focusRow(tableRows()[0]));
        }
    } catch { /* sin almacenamiento */ }

    // Pista visible en el botón «Nuevo».
    const nb = newButton();
    if (nb && !nb.title) nb.title = 'Atajo: Alt + N';
    nb?.setAttribute('aria-keyshortcuts', 'Alt+N');

    document.addEventListener('keydown', (e) => {
        if (e.defaultPrevented || e.isComposing) return;
        if (['Shift', 'Control', 'Alt', 'Meta', 'AltGraph', 'CapsLock'].includes(e.key)) return;

        const target = e.target;
        const typing = isTyping(target);
        const combo = comboOf(e);
        const locals = localKeys();
        if (locals.has(combo) || locals.has(e.key)) return;

        // Fila de tabla seleccionada.
        if (target instanceof HTMLTableRowElement && !e.ctrlKey && !e.altKey && !e.metaKey && handleRowKeys(e, target)) {
            e.preventDefault();
            return;
        }

        // Teclas de función: acceso directo a secciones.
        if (/^F\d{1,2}$/.test(e.key) && !e.ctrlKey && !e.altKey && !e.metaKey && !e.shiftKey && functions[e.key]) {
            e.preventDefault();
            go(functions[e.key]);
            return;
        }

        switch (combo) {
            case 'Ctrl+K':
                e.preventDefault();
                focusSearch();
                return;
            case 'Ctrl+S': {
                e.preventDefault();
                const form = formToSave();
                if (form) submit(form);
                else toast('No hay un formulario para guardar en esta pantalla');
                return;
            }
            case 'Ctrl+Enter': {
                const form = target?.form;
                if (form && (form.getAttribute('method') || 'get').toLowerCase() === 'post') {
                    e.preventDefault();
                    submit(form);
                }
                return;
            }
            case 'Alt+N': {
                e.preventDefault();
                const button = newButton();
                if (button) button.click();
                else toast('Esta pantalla no tiene «Nuevo»');
                return;
            }
            case 'Alt+M':
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('toggle-sidebar'));
                return;
            case 'Ctrl+Shift+E': {
                e.preventDefault();
                const link = exportButton();
                if (link) link.click();
                else toast('Esta pantalla no tiene exportación a Excel');
                return;
            }
            default:
                break;
        }

        // Esc saca el cursor del campo para poder usar las teclas de una letra.
        if (e.key === 'Escape' && typing && !target.closest('[data-local-keys], [role=dialog]')) {
            target.blur();
            return;
        }

        if (typing || e.ctrlKey || e.altKey || e.metaKey) return;

        // Secuencia G + letra.
        const key = e.key.toLowerCase();
        if (pendingG && Date.now() - pendingG < GO_TIMEOUT_MS) {
            pendingG = 0;
            if (goKeys[key]) {
                e.preventDefault();
                go(goKeys[key]);
            } else {
                toast(`No hay una sección con G + ${e.key.toUpperCase()}`);
            }
            return;
        }
        pendingG = 0;

        switch (e.key) {
            case 'g':
            case 'G':
                pendingG = Date.now();
                return;
            case '?':
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'shortcuts' }));
                return;
            case '/':
                e.preventDefault();
                focusSearch();
                return;
            case 't':
            case 'T': {
                const first = tableRows()[0];
                if (first) {
                    e.preventDefault();
                    prepareRows();
                    focusRow(first);
                }
                return;
            }
            case 'f':
            case 'F': {
                const field = main().querySelector('form[data-filters] input:not([type=hidden]), form[data-filters] select');
                if (field) {
                    e.preventDefault();
                    field.focus();
                }
                return;
            }
            default:
                break;
        }
    });
}

function focusSearch() {
    const input = document.querySelector('[data-global-search]');
    if (input) {
        input.focus();
        input.select();
    }
}
