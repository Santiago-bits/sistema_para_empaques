<?php

/*
|--------------------------------------------------------------------------
| Atajos de teclado
|--------------------------------------------------------------------------
| Fuente única: la pantalla «Ayuda y atajos» y la ventana que abre la tecla «?»
| se arman desde acá. El comportamiento está en resources/js/lib/shortcuts.js.
| 'go' = ruta que abre el atajo. Sólo se activan los atajos de secciones que el
| usuario ve en su menú (mismos permisos y módulos).
| F5, F6, F7, F10, F11 y F12 quedan para el navegador (recargar, pantalla completa…).
*/

return [
    'functions' => [
        // Teclas de función de acceso directo (como en el sistema anterior).
        'F1' => ['route' => 'help.shortcuts', 'label' => 'Ayuda y atajos'],
        'F2' => ['route' => 'production.scan', 'label' => 'Escanear cajones (registrar producción)'],
        'F3' => ['route' => 'loads.index', 'label' => 'Cargas (egreso de fruta)'],
        'F4' => ['route' => 'pallets.index', 'label' => 'Pallets (ingreso de fruta)'],
        'F8' => ['route' => 'crates.index', 'label' => 'Cajones'],
        'F9' => ['route' => 'dashboard', 'label' => 'Tablero (inicio)'],
    ],

    'groups' => [
        [
            'title' => 'Generales (en cualquier pantalla)',
            'items' => [
                ['keys' => ['?'], 'description' => 'Ver la lista de atajos de teclado'],
                ['keys' => ['/'], 'description' => 'Ir al buscador (cajón, pallet, carga, remito, CUIT, patente)'],
                ['keys' => ['Ctrl', 'K'], 'description' => 'Ir al buscador (alternativa)'],
                ['keys' => ['Alt', 'M'], 'description' => 'Abrir o cerrar el menú lateral'],
                ['keys' => ['Esc'], 'description' => 'Cerrar ventana, menú o cámara · salir del campo donde estás escribiendo'],
                ['keys' => ['Alt', 'N'], 'description' => 'Nuevo registro (en pantallas con botón «Nuevo»)'],
                ['keys' => ['Ctrl', 'S'], 'description' => 'Guardar el formulario'],
                ['keys' => ['Ctrl', 'Enter'], 'description' => 'Guardar / confirmar (formularios y control de calidad)'],
                ['keys' => ['Alt', '←'], 'description' => 'Volver a la pantalla anterior'],
                ['keys' => ['Ctrl', 'P'], 'description' => 'Imprimir la pantalla (remitos, facturas, etiquetas)'],
            ],
        ],
        [
            'title' => 'Teclas de función (acceso directo)',
            'functions' => true,
        ],
        [
            'title' => 'Ir a una sección (G y luego una letra)',
            'items' => [
                ['keys' => ['G', 'D'], 'description' => 'Tablero', 'go' => 'dashboard'],
                ['keys' => ['G', 'E'], 'description' => 'Escanear cajones', 'go' => 'production.scan'],
                ['keys' => ['G', 'J'], 'description' => 'Cajones', 'go' => 'crates.index'],
                ['keys' => ['G', 'P'], 'description' => 'Pallets', 'go' => 'pallets.index'],
                ['keys' => ['G', 'L'], 'description' => 'Lotes', 'go' => 'lots.index'],
                ['keys' => ['G', 'Q'], 'description' => 'Calidad', 'go' => 'quality.index'],
                ['keys' => ['G', 'C'], 'description' => 'Cargas', 'go' => 'loads.index'],
                ['keys' => ['G', 'R'], 'description' => 'Remitos', 'go' => 'remitos.index'],
                ['keys' => ['G', 'F'], 'description' => 'Facturación', 'go' => 'invoices.index'],
                ['keys' => ['G', 'B'], 'description' => 'Caja (ingresos, egresos, cierre)', 'go' => 'cash.index'],
                ['keys' => ['G', 'M'], 'description' => 'Cuentas corrientes (saldos)', 'go' => 'accounts.index'],
                ['keys' => ['G', 'V'], 'description' => 'Cheques (valores)', 'go' => 'checks.index'],
                ['keys' => ['G', 'O'], 'description' => 'Cotización del dólar', 'go' => 'exchange.index'],
                ['keys' => ['G', 'T'], 'description' => 'Reportes', 'go' => 'reports.index'],
                ['keys' => ['G', 'S'], 'description' => 'Estadísticas', 'go' => 'stats.index'],
                ['keys' => ['G', 'A'], 'description' => 'Alertas', 'go' => 'alerts.index'],
                ['keys' => ['G', 'N'], 'description' => 'Notificaciones', 'go' => 'notifications.index'],
                ['keys' => ['G', 'K'], 'description' => 'Fichas (productores, clientes, variedades…)', 'go' => 'catalogs.index'],
                ['keys' => ['G', 'H'], 'description' => 'Ayuda y atajos', 'go' => 'help.shortcuts'],
            ],
        ],
        [
            'title' => 'Listados y tablas (como en Excel)',
            'items' => [
                ['keys' => ['T'], 'description' => 'Ir a la tabla (primera fila)'],
                ['keys' => ['↑', '↓'], 'description' => 'Moverse fila por fila'],
                ['keys' => ['Inicio'], 'description' => 'Primera fila'],
                ['keys' => ['Fin'], 'description' => 'Última fila'],
                ['keys' => ['Enter'], 'description' => 'Abrir la fila seleccionada'],
                ['keys' => ['Re Pág'], 'description' => 'Página anterior del listado (estando en la tabla)'],
                ['keys' => ['Av Pág'], 'description' => 'Página siguiente del listado (estando en la tabla)'],
                ['keys' => ['F'], 'description' => 'Ir al primer filtro de búsqueda'],
                ['keys' => ['Ctrl', 'Shift', 'E'], 'description' => 'Exportar a Excel (en pantallas con exportación)'],
            ],
        ],
        [
            'title' => 'Escanear cajones y puesto fijo',
            'items' => [
                ['keys' => ['Enter'], 'description' => 'Confirmar el campo y pasar al siguiente (cajón → embalador → peso → guardar)'],
                ['keys' => ['F2'], 'description' => 'Volver al campo «Cajón»'],
                ['keys' => ['Esc'], 'description' => 'Limpiar todo y empezar de nuevo'],
                ['keys' => ['Tab'], 'description' => 'En el pedido de autorización: pasar de usuario a contraseña y motivo'],
            ],
        ],
        [
            'title' => 'Control de calidad',
            'items' => [
                ['keys' => ['F2'], 'description' => 'Resultado: aprobado'],
                ['keys' => ['F3'], 'description' => 'Resultado: rechazado (lleva el cursor al motivo)'],
                ['keys' => ['F4'], 'description' => 'Resultado: observado'],
                ['keys' => ['Ctrl', 'Enter'], 'description' => 'Registrar el control'],
            ],
        ],
        [
            'title' => 'Armado de cargas y movimientos',
            'items' => [
                ['keys' => ['Enter'], 'description' => 'Agregar el cajón o pallet escaneado a la carga'],
                ['keys' => ['Enter'], 'description' => 'Mover pallets: confirmar código y luego la ubicación de destino'],
            ],
        ],
    ],
];
