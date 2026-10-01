<?php

/*
|--------------------------------------------------------------------------
| Catálogo de permisos granulares
|--------------------------------------------------------------------------
| module => [slug => nombre]. El módulo define a qué funcionalidad activable
| pertenece: si el módulo está desactivado, sus permisos quedan bloqueados.
| Los roles por defecto se definen en `roles`. El Super Administrador tiene
| siempre acceso total y no necesita permisos asignados.
*/

return [
    'permissions' => [
        'core' => [
            'dashboard.view' => 'Ver dashboard',
            'users.view' => 'Ver usuarios',
            'users.manage' => 'Gestionar usuarios',
            'roles.manage' => 'Gestionar roles y permisos',
            'settings.manage' => 'Gestionar configuraciones',
            'modules.manage' => 'Activar/desactivar módulos',
            'audit.view' => 'Ver auditoría',
            'sessions.manage' => 'Ver y cerrar sesiones activas',
            'backups.manage' => 'Gestionar backups',
            'backups.restore' => 'Restaurar backups',
            'logs.view' => 'Ver logs técnicos',
            'support.use' => 'Crear tickets de soporte',
            'imports.manage' => 'Importar datos',
            'closings.manage' => 'Cierre diario',
            'closings.reopen' => 'Reabrir cierre diario',
            'alerts.view' => 'Ver alertas',
            'alerts.manage' => 'Configurar y resolver alertas',
            'api.tokens' => 'Gestionar tokens de API',
        ],
        'catalogs' => [
            'catalogs.view' => 'Ver catálogos',
            'catalogs.manage' => 'Gestionar catálogos',
            'packers.view' => 'Ver embaladores',
            'packers.manage' => 'Gestionar embaladores',
            'lots.view' => 'Ver lotes',
            'lots.manage' => 'Gestionar lotes',
        ],
        'pallets' => [
            'pallets.view' => 'Ver pallets',
            'pallets.create' => 'Crear pallets',
            'pallets.update' => 'Editar pallets',
            'pallets.void' => 'Anular pallets',
        ],
        'crates' => [
            'crates.view' => 'Ver cajones',
            'crates.create' => 'Crear cajones',
            'crates.update' => 'Editar cajones',
            'crates.delete' => 'Eliminar cajones',
            'crates.void' => 'Anular cajones',
            'labels.print' => 'Imprimir etiquetas',
            'traceability.view' => 'Consultar trazabilidad',
        ],
        'production' => [
            'production.scan' => 'Registrar producción (modo escaneo)',
            'production.view' => 'Ver producción',
            'production.void' => 'Anular registros de producción',
            'production.authorize' => 'Autorizar operaciones fuera de parámetros',
            'production.own' => 'Ver mi producción (embalador)',
            'stoppages.manage' => 'Registrar paradas de producción',
            'shifts.manage' => 'Gestionar turnos, líneas y objetivos',
        ],
        'quality' => [
            'quality.view' => 'Ver controles de calidad',
            'quality.manage' => 'Registrar controles y rechazos',
        ],
        'locations' => [
            'locations.view' => 'Ver ubicaciones y mapa',
            'locations.manage' => 'Gestionar ubicaciones',
            'locations.move' => 'Mover pallets/cajones',
        ],
        'loads' => [
            'loads.view' => 'Ver cargas',
            'loads.create' => 'Crear cargas',
            'loads.update' => 'Modificar cargas (asignar cajones)',
            'loads.close' => 'Cerrar cargas',
            'loads.reopen' => 'Reabrir cargas cerradas',
            'loads.dispatch' => 'Despachar (checklist)',
            'loads.cancel' => 'Cancelar cargas',
        ],
        'remitos' => [
            'remitos.view' => 'Ver remitos',
            'remitos.create' => 'Emitir remitos',
            'remitos.deliver' => 'Registrar entregas',
            'remitos.void' => 'Anular remitos',
        ],
        'documents' => [
            'documents.view' => 'Ver documentos',
            'documents.manage' => 'Adjuntar/eliminar documentos',
        ],
        'reports' => [
            'reports.view' => 'Ver reportes',
            'reports.export_excel' => 'Exportar Excel/CSV',
            'reports.export_pdf' => 'Exportar PDF',
            'stats.view' => 'Ver estadísticas',
        ],
        'billing' => [
            'billing.view' => 'Ver facturación',
            'billing.manage' => 'Crear/editar comprobantes',
            'billing.void' => 'Anular comprobantes',
        ],
        'arca' => [
            'arca.manage' => 'Gestionar ARCA (envío, configuración)',
        ],
        'supplies' => [
            'supplies.view' => 'Ver insumos',
            'supplies.manage' => 'Gestionar insumos y movimientos',
        ],
        'incidents' => [
            'incidents.view' => 'Ver incidentes',
            'incidents.manage' => 'Registrar/resolver incidentes',
        ],
        'maintenance' => [
            'maintenance.view' => 'Ver mantenimiento',
            'maintenance.manage' => 'Gestionar máquinas y mantenimientos',
        ],
        'cold_rooms' => [
            'cold_rooms.view' => 'Ver cámaras frigoríficas',
            'cold_rooms.manage' => 'Registrar temperaturas y gestionar cámaras',
        ],
        'costs' => [
            'costs.view' => 'Ver costos',
            'costs.manage' => 'Gestionar costos',
            'profit.view' => 'Ver ganancias/rentabilidad',
        ],
        'client_portal' => [
            'portal.view' => 'Acceso al portal de cliente/propietario',
        ],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Super Administrador / Desarrollador',
            'description' => 'Acceso total al sistema, incluido el panel del desarrollador.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Administrador del galpón',
            'description' => 'Administración general del negocio.',
            'permissions' => ['*', '!backups.restore', '!logs.view'],
        ],
        'intake_operator' => [
            'name' => 'Operador de ingreso',
            'description' => 'Registra pallets, cajones y escaneos.',
            'permissions' => [
                'dashboard.view', 'catalogs.view', 'packers.view', 'lots.view', 'lots.manage',
                'pallets.view', 'pallets.create', 'pallets.update', 'crates.view', 'crates.create', 'crates.update',
                'labels.print', 'traceability.view', 'production.scan', 'production.view', 'stoppages.manage',
                'locations.view', 'locations.move', 'support.use', 'alerts.view', 'incidents.view', 'incidents.manage',
            ],
        ],
        'packer' => [
            'name' => 'Embalador',
            'description' => 'Consulta su propia producción.',
            'permissions' => ['production.own'],
        ],
        'loads_operator' => [
            'name' => 'Operador de cargas',
            'description' => 'Organiza camiones y cargas.',
            'permissions' => [
                'dashboard.view', 'catalogs.view', 'crates.view', 'pallets.view', 'traceability.view',
                'locations.view', 'locations.move', 'loads.view', 'loads.create', 'loads.update', 'loads.close',
                'loads.dispatch', 'remitos.view', 'remitos.create', 'remitos.deliver', 'documents.view',
                'documents.manage', 'support.use', 'alerts.view', 'incidents.view', 'incidents.manage',
            ],
        ],
        'quality' => [
            'name' => 'Control de calidad',
            'description' => 'Controles de calidad, rechazos y observaciones.',
            'permissions' => [
                'dashboard.view', 'catalogs.view', 'lots.view', 'crates.view', 'pallets.view', 'traceability.view',
                'quality.view', 'quality.manage', 'production.view', 'reports.view', 'support.use', 'alerts.view',
                'incidents.view', 'incidents.manage', 'cold_rooms.view', 'cold_rooms.manage',
            ],
        ],
        'billing' => [
            'name' => 'Facturación / Administración',
            'description' => 'Documentación, facturas y ARCA.',
            'permissions' => [
                'dashboard.view', 'catalogs.view', 'catalogs.manage', 'loads.view', 'remitos.view', 'remitos.create',
                'remitos.void', 'documents.view', 'documents.manage', 'billing.view', 'billing.manage', 'billing.void',
                'arca.manage', 'reports.view', 'reports.export_excel', 'reports.export_pdf', 'costs.view',
                'costs.manage', 'support.use', 'alerts.view', 'supplies.view', 'supplies.manage',
            ],
        ],
        'supervisor' => [
            'name' => 'Supervisor',
            'description' => 'Consulta estadísticas y controla operaciones.',
            'permissions' => [
                'dashboard.view', 'catalogs.view', 'packers.view', 'lots.view', 'pallets.view', 'crates.view',
                'crates.void', 'traceability.view', 'production.view', 'production.void', 'production.authorize',
                'stoppages.manage', 'quality.view', 'locations.view', 'loads.view', 'loads.reopen', 'remitos.view',
                'documents.view', 'reports.view', 'reports.export_excel', 'reports.export_pdf', 'stats.view',
                'audit.view', 'closings.manage', 'alerts.view', 'alerts.manage', 'incidents.view', 'incidents.manage',
                'supplies.view', 'maintenance.view', 'cold_rooms.view', 'support.use',
            ],
        ],
        'client_portal' => [
            'name' => 'Cliente / Propietario',
            'description' => 'Consulta la información de su mercadería.',
            'permissions' => ['portal.view'],
        ],
    ],
];
