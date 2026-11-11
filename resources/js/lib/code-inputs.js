/**
 * Campos de código con numeración (cajón «CJ-», pallet «PAL-», lote «LOT-», carga «CARG-», remito «0001-»):
 * el prefijo se muestra fijo al lado y alcanza con escribir el número. Al apretar Enter, salir del campo o
 * enviar el formulario, «123» pasa a ser «CJ-000123». Un lector de códigos que tipea el código completo
 * también funciona (el prefijo se saca de la vista y se vuelve a poner al confirmar).
 * Se activa con data-code-prefix / data-code-padding (helper code_attrs() o <x-input code="crate">).
 */
const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

function parts(input) {
    return { prefix: input.dataset.codePrefix ?? '', padding: parseInt(input.dataset.codePadding || '6', 10) };
}

/** Código completo a partir de lo escrito (sólo números, o el código con menos ceros). Lo demás queda igual. */
export function normalizeCode(value, prefix, padding) {
    const v = String(value ?? '').trim();
    const m = v.match(new RegExp(`^(?:${escape(prefix)})?(\\d+)$`, 'i'));
    if (!m || v === '') return v;
    const digits = m[1].replace(/^0+(?=\d)/, '');
    return prefix + digits.padStart(padding, '0');
}

function apply(input) {
    const { prefix, padding } = parts(input);
    const next = normalizeCode(input.value, prefix, padding);
    if (next !== input.value) {
        input.value = next;
        // Mientras se avisa el cambio (x-model, @input), no se le vuelve a sacar el prefijo de la vista.
        input.dataset.codeApplying = '1';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        delete input.dataset.codeApplying;
    }
}

function enhance(input) {
    if (input.dataset.codeReady) return;
    input.dataset.codeReady = '1';
    const { prefix } = parts(input);
    if (prefix && input.dataset.codeAddon !== 'off') {
        const wrap = document.createElement('div');
        wrap.className = 'flex';
        const addon = document.createElement('span');
        addon.className = 'inline-flex shrink-0 items-center border border-r-0 border-stone-300 bg-stone-100 px-3 font-mono text-sm text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300';
        addon.textContent = prefix;
        addon.setAttribute('aria-hidden', 'true');
        input.parentNode.insertBefore(wrap, input);
        wrap.append(addon, input);
        // Mientras se escribe: si llega el código completo (lector), se muestra sólo el número junto al prefijo.
        input.addEventListener('input', () => {
            const m = input.value.match(new RegExp(`^${escape(prefix)}(\\d*)$`, 'i'));
            if (m && !input.dataset.codeApplying && document.activeElement === input) input.value = m[1];
        });
        // Al cargar la página con un código ya completo (filtro aplicado), se muestra sólo el número.
        const m = input.value.match(new RegExp(`^${escape(prefix)}(\\d+)$`, 'i'));
        if (m) input.value = m[1].replace(/^0+(?=\d)/, '');
        if (!input.placeholder || input.placeholder.startsWith(prefix)) input.placeholder = 'Número';
    }
    // Sin prefijo a la vista, se completa también al salir del campo (con prefijo, al confirmar: Enter o enviar).
    if (!prefix || input.dataset.codeAddon === 'off') input.addEventListener('blur', () => apply(input));
}

export function initCodeInputs(root = document) {
    root.querySelectorAll?.('input[data-code-prefix]').forEach(enhance);
}

export function watchCodeInputs() {
    // Enter: se completa ANTES de que lo lean los manejadores de la pantalla (fase de captura).
    document.addEventListener('keydown', (e) => {
        const input = e.target;
        if (e.key !== 'Enter' || !(input instanceof HTMLInputElement) || input.dataset.codePrefix === undefined) return;
        apply(input);
        // La pantalla ya recibió el código completo; a la vista vuelve a quedar sólo el número junto al prefijo.
        const { prefix } = parts(input);
        if (prefix && input.dataset.codeAddon !== 'off') {
            setTimeout(() => {
                const m = input.value.match(new RegExp(`^${escape(prefix)}(\\d+)$`, 'i'));
                if (m) input.value = m[1].replace(/^0+(?=\d)/, '');
            }, 0);
        }
    }, true);
    document.addEventListener('submit', (e) => {
        e.target.querySelectorAll?.('input[data-code-prefix]').forEach(apply);
    }, true);
    new MutationObserver((mutations) => {
        for (const m of mutations) m.addedNodes.forEach((n) => n.nodeType === 1 && (n.matches?.('input[data-code-prefix]') ? enhance(n) : initCodeInputs(n)));
    }).observe(document.body, { childList: true, subtree: true });
}
