/**
 * Tablas sin desplazamiento hacia el costado: si una tabla no entra en el ancho disponible, se muestra como
 * tarjetas (clase .table-cards, ver resources/css/app.css). Cada celda lleva el nombre de su columna, que se
 * copia del encabezado. Se vuelve a evaluar cuando cambia el ancho (girar el celular, achicar la ventana).
 */
function label(table) {
    const labels = [];
    table.querySelectorAll('thead tr:last-child th').forEach((th) => {
        const span = Number(th.getAttribute('colspan') || 1);
        for (let i = 0; i < span; i++) labels.push(th.textContent.trim());
    });
    table.querySelectorAll('tbody tr').forEach((tr) => {
        let index = 0;
        Array.from(tr.children).forEach((cell) => {
            if (!cell.hasAttribute('data-label')) cell.setAttribute('data-label', labels[index] ?? '');
            index += Number(cell.getAttribute('colspan') || 1);
        });
    });
}

function fit(table) {
    const box = table.parentElement;
    if (!box || box.clientWidth === 0) return; // oculta (pestaña cerrada): se evalúa al mostrarse
    table.classList.remove('table-cards');
    if (table.scrollWidth > box.clientWidth + 1) table.classList.add('table-cards');
}

export function labelTables(root = document) {
    const tables = root.querySelectorAll('table.table');
    tables.forEach((table) => {
        label(table);
        fit(table);
    });
    if (typeof ResizeObserver === 'undefined') return;
    const observer = new ResizeObserver((entries) => {
        entries.forEach((entry) => {
            const table = entry.target.querySelector(':scope > table.table');
            if (table) fit(table);
        });
    });
    tables.forEach((table) => table.parentElement && observer.observe(table.parentElement));
}
