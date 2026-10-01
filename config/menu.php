<?php

/*
|--------------------------------------------------------------------------
| Menú lateral
|--------------------------------------------------------------------------
| Cada ítem se muestra sólo si: la ruta existe, el módulo está activo y el
| usuario tiene el permiso. `permission` puede ser un array (alcanza con uno).
*/

return [
    [
        'title' => null,
        'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view', 'module' => 'core'],
            ['label' => 'Mi producción', 'route' => 'production.mine', 'icon' => 'user-chart', 'permission' => 'production.own', 'module' => 'production'],
            ['label' => 'Mi mercadería', 'route' => 'portal.index', 'icon' => 'briefcase', 'permission' => 'portal.view', 'module' => 'client_portal'],
        ],
    ],
    [
        'title' => 'Producción',
        'items' => [
            ['label' => 'Modo escaneo', 'route' => 'production.scan', 'icon' => 'scan', 'permission' => 'production.scan', 'module' => 'production', 'highlight' => true],
            ['label' => 'Pallets', 'route' => 'pallets.index', 'icon' => 'pallet', 'permission' => 'pallets.view', 'module' => 'pallets'],
            ['label' => 'Lotes', 'route' => 'lots.index', 'icon' => 'layers', 'permission' => 'lots.view', 'module' => 'catalogs'],
            ['label' => 'Cajones', 'route' => 'crates.index', 'icon' => 'box', 'permission' => 'crates.view', 'module' => 'crates'],
            ['label' => 'Registros de producción', 'route' => 'production.index', 'icon' => 'list', 'permission' => 'production.view', 'module' => 'production'],
            ['label' => 'Paradas', 'route' => 'stoppages.index', 'icon' => 'pause', 'permission' => 'stoppages.manage', 'module' => 'production'],
            ['label' => 'Calidad', 'route' => 'quality.index', 'icon' => 'check-badge', 'permission' => 'quality.view', 'module' => 'quality'],
            ['label' => 'Rechazos y merma', 'route' => 'rejects.index', 'icon' => 'trash', 'permission' => 'quality.view', 'module' => 'quality'],
            ['label' => 'Trazabilidad', 'route' => 'traceability.index', 'icon' => 'route', 'permission' => 'traceability.view', 'module' => 'crates'],
        ],
    ],
    [
        'title' => 'Galpón',
        'items' => [
            ['label' => 'Mapa del galpón', 'route' => 'locations.map', 'icon' => 'map', 'permission' => 'locations.view', 'module' => 'locations'],
            ['label' => 'Ubicaciones', 'route' => 'locations.index', 'icon' => 'pin', 'permission' => 'locations.view', 'module' => 'locations'],
            ['label' => 'Cámaras frigoríficas', 'route' => 'cold-rooms.index', 'icon' => 'snow', 'permission' => 'cold_rooms.view', 'module' => 'cold_rooms'],
            ['label' => 'Insumos', 'route' => 'supplies.index', 'icon' => 'archive', 'permission' => 'supplies.view', 'module' => 'supplies'],
            ['label' => 'Mantenimiento', 'route' => 'machines.index', 'icon' => 'wrench', 'permission' => 'maintenance.view', 'module' => 'maintenance'],
            ['label' => 'Incidentes', 'route' => 'incidents.index', 'icon' => 'alert', 'permission' => 'incidents.view', 'module' => 'incidents'],
        ],
    ],
    [
        'title' => 'Logística',
        'items' => [
            ['label' => 'Cargas', 'route' => 'loads.index', 'icon' => 'truck', 'permission' => 'loads.view', 'module' => 'loads'],
            ['label' => 'Remitos', 'route' => 'remitos.index', 'icon' => 'document', 'permission' => 'remitos.view', 'module' => 'remitos'],
            ['label' => 'Documentos', 'route' => 'documents.index', 'icon' => 'folder', 'permission' => 'documents.view', 'module' => 'documents'],
        ],
    ],
    [
        'title' => 'Administración',
        'items' => [
            ['label' => 'Facturación', 'route' => 'invoices.index', 'icon' => 'receipt', 'permission' => 'billing.view', 'module' => 'billing'],
            ['label' => 'ARCA', 'route' => 'arca.index', 'icon' => 'bank', 'permission' => 'arca.manage', 'module' => 'arca'],
            ['label' => 'Costos', 'route' => 'costs.index', 'icon' => 'currency', 'permission' => 'costs.view', 'module' => 'costs'],
            ['label' => 'Catálogos', 'route' => 'catalogs.index', 'icon' => 'book', 'permission' => ['catalogs.view', 'packers.view'], 'module' => 'catalogs'],
        ],
    ],
    [
        'title' => 'Informes',
        'items' => [
            ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'chart-bar', 'permission' => 'reports.view', 'module' => 'reports'],
            ['label' => 'Estadísticas', 'route' => 'stats.index', 'icon' => 'chart-line', 'permission' => 'stats.view', 'module' => 'reports'],
            ['label' => 'Cierre diario', 'route' => 'closings.index', 'icon' => 'lock', 'permission' => 'closings.manage', 'module' => 'core'],
        ],
    ],
    [
        'title' => 'Sistema',
        'items' => [
            ['label' => 'Usuarios', 'route' => 'admin.users.index', 'icon' => 'users', 'permission' => 'users.view', 'module' => 'core'],
            ['label' => 'Roles y permisos', 'route' => 'admin.roles.index', 'icon' => 'shield', 'permission' => 'roles.manage', 'module' => 'core'],
            ['label' => 'Módulos', 'route' => 'admin.modules.index', 'icon' => 'puzzle', 'permission' => 'modules.manage', 'module' => 'core'],
            ['label' => 'Configuración', 'route' => 'admin.settings.index', 'icon' => 'cog', 'permission' => 'settings.manage', 'module' => 'core'],
            ['label' => 'Alertas', 'route' => 'alerts.index', 'icon' => 'bell', 'permission' => 'alerts.view', 'module' => 'core'],
            ['label' => 'Importar datos', 'route' => 'imports.index', 'icon' => 'upload', 'permission' => 'imports.manage', 'module' => 'core'],
            ['label' => 'Auditoría', 'route' => 'admin.audit.index', 'icon' => 'eye', 'permission' => 'audit.view', 'module' => 'core'],
            ['label' => 'Sesiones activas', 'route' => 'admin.sessions.index', 'icon' => 'signal', 'permission' => 'sessions.manage', 'module' => 'core'],
            ['label' => 'Backups', 'route' => 'backups.index', 'icon' => 'database', 'permission' => 'backups.manage', 'module' => 'core'],
            ['label' => 'Tokens de API', 'route' => 'admin.tokens.index', 'icon' => 'key', 'permission' => 'api.tokens', 'module' => 'core'],
            ['label' => 'Soporte', 'route' => 'support.index', 'icon' => 'lifebuoy', 'permission' => 'support.use', 'module' => 'core'],
            ['label' => 'Panel desarrollador', 'route' => 'developer.index', 'icon' => 'code', 'permission' => 'developer', 'module' => 'core'],
        ],
    ],
];
