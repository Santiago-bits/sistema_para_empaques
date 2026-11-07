/**
 * Listas con búsqueda: los <select> con muchas opciones (clientes, camioneros, transportistas, empleados,
 * variedades, …) se convierten en un campo donde se escribe para filtrar y se elige con ↑ ↓ y Enter.
 *
 * El <select> original queda en la página (oculto) y es el que se envía con el formulario: los cambios se
 * le aplican con eventos `input`/`change`, así que x-model, @change y los scripts que lo leen siguen igual.
 * Para dejar un select como estaba: atributo `data-native`.
 */
const MIN_OPTIONS = 6;
const instances = new Set();

const normalize = (text) => text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();

function shouldEnhance(select) {
    if (select.dataset.searchable === 'off' || select.hasAttribute('data-native') || select.dataset.enhanced) return false;
    if (select.multiple || select.disabled || select.size > 1) return false;
    // Pantallas con su propio manejo de teclado o foco (p. ej. el escaneo): no se tocan.
    if ([...select.attributes].some((a) => a.name === 'x-ref' || a.name.includes('keydown'))) return false;
    const real = [...select.options].filter((o) => o.value !== '');
    return select.dataset.searchable === 'on' || real.length >= MIN_OPTIONS;
}

function enhance(select) {
    select.dataset.enhanced = '1';
    const uid = `ss-${Math.random().toString(36).slice(2, 9)}`;

    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.className = `${select.className} pr-9`;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', uid);
    if (select.required) input.setAttribute('aria-required', 'true');
    wrapper.appendChild(input);

    const caret = document.createElement('span');
    caret.className = 'pointer-events-none absolute inset-y-0 right-3 flex items-center text-stone-400';
    caret.innerHTML = '<svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>';
    wrapper.appendChild(caret);

    const list = document.createElement('ul');
    list.id = uid;
    list.setAttribute('role', 'listbox');
    list.className = 'fixed z-50 max-h-72 overflow-y-auto rounded-none border border-stone-200 bg-white py-1 text-sm shadow-lg dark:border-stone-700 dark:bg-stone-900';
    list.hidden = true;
    document.body.appendChild(list);

    // El select queda para el formulario, fuera de la vista y del orden de tabulación.
    select.classList.add('sr-only');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((label) => {
        if (select.id) label.addEventListener('click', (e) => { e.preventDefault(); input.focus(); });
    });

    let matches = [];
    let highlighted = -1;
    let typed = false;
    let lastIndex = -1;

    const selectedOption = () => select.options[select.selectedIndex] ?? null;
    const placeholderOption = () => [...select.options].find((o) => o.value === '') ?? null;

    function showSelected() {
        const opt = selectedOption();
        input.value = opt && opt.value !== '' ? opt.text.trim() : '';
        input.placeholder = placeholderOption()?.text.trim() || 'Escribí para buscar…';
        input.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        lastIndex = select.selectedIndex;
    }

    function position() {
        const r = input.getBoundingClientRect();
        const below = window.innerHeight - r.bottom;
        // Al menos 16rem de ancho para que los nombres largos entren en una línea, sin salirse de la pantalla.
        const width = Math.min(Math.max(r.width, 256), window.innerWidth - 16);
        list.style.width = `${width}px`;
        list.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - width - 8))}px`;
        if (below < 220 && r.top > below) {
            list.style.top = '';
            list.style.bottom = `${window.innerHeight - r.top + 4}px`;
        } else {
            list.style.bottom = '';
            list.style.top = `${r.bottom + 4}px`;
        }
    }

    function render() {
        const words = typed ? normalize(input.value).split(/\s+/).filter(Boolean) : [];
        matches = [...select.options].filter((o) => !o.disabled && (words.length === 0
            || (o.value !== '' && words.every((w) => normalize(o.text).includes(w)))));
        list.innerHTML = '';
        if (matches.length === 0) {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-stone-500 dark:text-stone-400';
            li.textContent = 'No hay coincidencias';
            list.appendChild(li);
        }
        matches.forEach((opt, i) => {
            const li = document.createElement('li');
            li.id = `${uid}-${i}`;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(i === highlighted));
            li.className = 'cursor-pointer px-3 py-2 text-stone-800 dark:text-stone-200';
            if (opt.value === '') li.classList.add('text-stone-500', 'dark:text-stone-400');
            if (i === highlighted) li.classList.add('bg-brand-600', 'text-white', 'dark:bg-brand-600');
            li.textContent = opt.text.trim();
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(opt); });
            list.appendChild(li);
        });
        const hint = document.createElement('li');
        hint.className = 'border-t border-stone-100 px-3 pt-1.5 pb-1 text-[11px] text-stone-400 dark:border-stone-800';
        hint.textContent = '↑ ↓ para moverte · Enter para elegir · Esc para salir';
        list.appendChild(hint);
        input.setAttribute('aria-activedescendant', highlighted >= 0 ? `${uid}-${highlighted}` : '');
        list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }

    function open() {
        if (!list.hidden) return;
        typed = false;
        highlighted = Math.max(0, [...select.options].filter((o) => !o.disabled).indexOf(selectedOption()));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        position();
        render();
    }

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        showSelected();
    }

    function choose(opt) {
        const changed = select.value !== opt.value;
        select.value = opt.value;
        close();
        if (changed) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function move(delta) {
        if (list.hidden) { open(); return; }
        if (matches.length === 0) return;
        highlighted = (highlighted + delta + matches.length) % matches.length;
        render();
    }

    input.addEventListener('focus', () => { showSelected(); input.select(); });
    input.addEventListener('click', open);
    input.addEventListener('blur', () => close());
    input.addEventListener('input', () => {
        if (list.hidden) { list.hidden = false; input.setAttribute('aria-expanded', 'true'); position(); }
        typed = true;
        highlighted = 0;
        render();
    });
    input.addEventListener('keydown', (e) => {
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); move(1); break;
            case 'ArrowUp': e.preventDefault(); move(-1); break;
            case 'PageDown': if (!list.hidden) { e.preventDefault(); move(8); } break;
            case 'PageUp': if (!list.hidden) { e.preventDefault(); move(-8); } break;
            case 'Enter':
                // Con la lista abierta, Enter elige (no envía el formulario).
                if (!list.hidden) {
                    e.preventDefault();
                    if (matches[highlighted]) choose(matches[highlighted]); else close();
                }
                break;
            case 'Escape':
                if (!list.hidden) { e.preventDefault(); e.stopPropagation(); close(); }
                break;
            case 'Tab':
                if (!list.hidden && typed && matches[highlighted]) choose(matches[highlighted]);
                break;
            default:
        }
    });

    select.addEventListener('change', showSelected);
    select.addEventListener('invalid', () => { input.classList.add('is-invalid'); input.focus(); });
    window.addEventListener('resize', () => { if (!list.hidden) position(); });
    document.addEventListener('scroll', (e) => { if (!list.hidden && !list.contains(e.target)) position(); }, true);

    showSelected();
    instances.add({ select, input, list, sync: () => { if (select.selectedIndex !== lastIndex && document.activeElement !== input) showSelected(); } });
}

export function initSearchableSelects(root = document) {
    root.querySelectorAll?.('select').forEach((select) => { if (shouldEnhance(select)) enhance(select); });
}

/** Selects que aparecen después (modales, partes que carga Alpine) y valores que cambia Alpine (x-model). */
export function watchSearchableSelects() {
    new MutationObserver((mutations) => {
        for (const m of mutations) {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'SELECT') { if (shouldEnhance(node)) enhance(node); } else initSearchableSelects(node);
            });
        }
    }).observe(document.body, { childList: true, subtree: true });

    setInterval(() => {
        instances.forEach((inst) => {
            if (!inst.select.isConnected) { inst.list.remove(); instances.delete(inst); return; }
            inst.sync();
        });
    }, 400);
}
