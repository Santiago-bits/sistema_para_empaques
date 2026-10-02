<?php

/*
|--------------------------------------------------------------------------
| Sectores de trabajo
|--------------------------------------------------------------------------
| Forma simple de dar acceso a un empleado: el dueño tilda los sectores que
| atiende y el sistema le asigna los permisos de cada uno (rol «Empleado por
| sectores» + permisos individuales). «Acceso total» = rol Administrador.
| Cada permiso debe existir en config/permissions.php.
*/

return [
    'labels' => [
        'label' => 'Etiquetas',
        'description' => 'Imprimir y reimprimir etiquetas de cajones y pallets.',
        'icon' => 'printer',
        'permissions' => ['labels.print', 'crates.view', 'pallets.view', 'lots.view', 'catalogs.view'],
    ],
    'weighing' => [
        'label' => 'Romaneo y producción',
        'description' => 'Pesaje y registro de cajones (modo escaneo), embaladores y paradas de línea.',
        'icon' => 'scale',
        'permissions' => ['production.scan', 'production.view', 'crates.view', 'crates.create', 'crates.update', 'packers.view',
            'lots.view', 'pallets.view', 'stoppages.manage', 'traceability.view'],
    ],
    'intake' => [
        'label' => 'Ingreso de fruta',
        'description' => 'Lotes de productores, pallets y cajones que entran al galpón.',
        'icon' => 'pallet',
        'permissions' => ['lots.view', 'lots.manage', 'pallets.view', 'pallets.create', 'pallets.update', 'crates.view', 'crates.create',
            'labels.print', 'catalogs.view', 'traceability.view'],
    ],
    'quality' => [
        'label' => 'Calidad',
        'description' => 'Controles de calidad, rechazos y merma.',
        'icon' => 'check-badge',
        'permissions' => ['quality.view', 'quality.manage', 'crates.view', 'pallets.view', 'lots.view', 'traceability.view'],
    ],
    'warehouse' => [
        'label' => 'Galpón y cámaras',
        'description' => 'Ubicaciones, mover pallets y temperaturas de cámaras frigoríficas.',
        'icon' => 'map',
        'permissions' => ['locations.view', 'locations.move', 'locations.manage', 'cold_rooms.view', 'cold_rooms.manage', 'pallets.view', 'crates.view'],
    ],
    'loads' => [
        'label' => 'Cargas y despacho',
        'description' => 'Armado de camiones, remitos, checklist de despacho y entregas.',
        'icon' => 'truck',
        'permissions' => ['loads.view', 'loads.create', 'loads.update', 'loads.close', 'loads.dispatch', 'remitos.view', 'remitos.create',
            'remitos.deliver', 'documents.view', 'documents.manage', 'crates.view', 'pallets.view', 'catalogs.view', 'locations.view',
            'locations.move', 'traceability.view'],
    ],
    'billing' => [
        'label' => 'Facturación',
        'description' => 'Facturas y notas de crédito con ARCA, clientes y documentación.',
        'icon' => 'receipt',
        'permissions' => ['billing.view', 'billing.manage', 'billing.void', 'arca.manage', 'remitos.view', 'loads.view', 'catalogs.view',
            'catalogs.manage', 'documents.view'],
    ],
    'treasury' => [
        'label' => 'Contabilidad y tesorería',
        'description' => 'Caja, cuentas corrientes, cheques, cotización del dólar, costos y liquidación a productores.',
        'icon' => 'currency',
        'permissions' => ['treasury.view', 'cash.manage', 'accounts.manage', 'checks.manage', 'exchange.manage', 'lots.settle', 'lots.view',
            'costs.view', 'costs.manage', 'profit.view', 'billing.view', 'catalogs.view'],
    ],
    'supplies' => [
        'label' => 'Insumos y mantenimiento',
        'description' => 'Stock de materiales, máquinas y mantenimientos.',
        'icon' => 'archive',
        'permissions' => ['supplies.view', 'supplies.manage', 'maintenance.view', 'maintenance.manage'],
    ],
    'reports' => [
        'label' => 'Reportes y estadísticas',
        'description' => 'Informes, exportación a Excel/PDF y estadísticas.',
        'icon' => 'chart-bar',
        'permissions' => ['reports.view', 'reports.export_excel', 'reports.export_pdf', 'stats.view'],
    ],
    'staff' => [
        'label' => 'Personal y catálogos',
        'description' => 'Empleados, cuadrillas, embaladores, turnos y catálogos (productores, clientes, variedades…).',
        'icon' => 'users',
        'permissions' => ['staff.view', 'staff.manage', 'catalogs.view', 'catalogs.manage', 'packers.view', 'packers.manage', 'shifts.manage'],
    ],
    'incidents' => [
        'label' => 'Incidentes',
        'description' => 'Registrar y resolver problemas (faltantes, daños, transporte).',
        'icon' => 'alert',
        'permissions' => ['incidents.view', 'incidents.manage'],
    ],
];
