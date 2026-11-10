<?php

namespace App\Support;

/**
 * Multigalpón: un solo sistema con muchos galpones, cada uno con sus propios datos.
 *
 * Toda tabla de esta lista lleva warehouse_id y su modelo usa BelongsToWarehouse: las consultas Eloquent
 * traen sólo lo del galpón activo y lo que se crea queda en el galpón activo. Las reglas de validación
 * «unique» y «exists» sobre estas tablas también miran sólo el galpón activo (WarehousePresenceVerifier),
 * así dos galpones pueden repetir códigos y nadie puede elegir un registro de otro galpón.
 *
 * Las consultas con DB::table() NO pasan por ese filtro: deben filtrar a mano con
 * ->where('<tabla>.warehouse_id', CurrentWarehouse::id()).
 */
class Tenancy
{
    /** Tablas con datos de cada galpón. */
    public const TABLES = [
        'account_movements', 'alerts', 'arca_records', 'audit_logs', 'cash_movements', 'cash_sessions', 'checks',
        'clients', 'cold_rooms', 'container_movements', 'container_types', 'costs', 'crates', 'crews', 'daily_closings',
        'destinations', 'dispatch_checks', 'documents', 'drivers', 'dtv_documents', 'dtv_lines', 'employees', 'grades',
        'import_batches', 'incidents', 'inventory_movements', 'invoice_items', 'invoices', 'load_crates', 'loads',
        'location_movements', 'lots', 'machines', 'maintenances', 'modules', 'owners', 'packers', 'pallets', 'producers',
        'production_lines', 'production_records', 'production_stoppages', 'production_targets', 'providers',
        'quality_controls', 'reasons', 'rejects', 'remito_items', 'remitos', 'seasons', 'sequences', 'shifts', 'sizes',
        'state_histories', 'supplies', 'supply_yields', 'support_ticket_replies', 'support_tickets', 'temperature_records',
        'transporters', 'treatments', 'trucks', 'varieties', 'warehouse_locations',
    ];

    /**
     * Tablas que tienen warehouse_id pero admiten filas comunes a todos (warehouse_id NULL):
     * roles del sistema y configuración general (correo de soporte, backups, Panel General).
     */
    public const SHARED_TABLES = ['roles', 'settings'];

    public static function isTenantTable(string $table): bool
    {
        return in_array($table, self::TABLES, true);
    }
}
