<?php

use App\Services\SettingsService;
use App\Support\Tenancy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Multigalpón: un solo sistema para muchos galpones, cada uno con sus propios datos.
 *
 * - Cada tabla de datos de un galpón (Tenancy::TABLES) recibe warehouse_id; lo que ya había queda en el
 *   primer galpón (el que se venía usando). Nada se borra ni se mueve de lugar.
 * - Los códigos que no se podían repetir (código de productor, CUIT, patente, N° de cajón…) pasan a no poder
 *   repetirse DENTRO de cada galpón: dos galpones pueden tener cada uno su «PR-001».
 * - Cada usuario pertenece a un galpón (users.warehouse_id); el Super Administrador no tiene (los ve a todos).
 * - Configuración y roles: filas comunes (warehouse_id NULL) y filas de cada galpón.
 *
 * Es segura de correr desde una petición web (DatabaseUpgrader) y de volver a correr: cada paso se salta si ya está hecho.
 */
return new class extends Migration
{
    public function up(): void
    {
        $warehouseId = $this->firstWarehouseId();

        if (! Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->index()->after('id');
            });
            // Todos los usuarios que no son Super Administrador quedan en el galpón que se venía usando.
            $superRole = DB::table('roles')->where('slug', 'super_admin')->value('id');
            DB::table('users')->when($superRole, fn ($q) => $q->where(fn ($w) => $w->whereNull('role_id')->orWhere('role_id', '!=', $superRole)))
                ->update(['warehouse_id' => $warehouseId]);
        }

        foreach (array_merge(Tenancy::TABLES, Tenancy::SHARED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('warehouse_id')->nullable()->index();
                });
            }
            if (Tenancy::isTenantTable($table) && $warehouseId) {
                DB::table($table)->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
            }
            $this->scopeUniqueIndexes($table);
        }

        // Configuración: lo general queda común; lo de la empresa, etiquetas, ARCA, producción, etc. pasa al primer galpón.
        if ($warehouseId && Schema::hasTable('settings')) {
            $settings = DB::table('settings')->whereNull('warehouse_id')->get(['id', 'key']);
            foreach ($settings as $setting) {
                if (! SettingsService::isGlobal($setting->key)) {
                    DB::table('settings')->where('id', $setting->id)->update(['warehouse_id' => $warehouseId]);
                }
            }
        }
        // Roles creados por el galpón (no los del sistema) pasan a ser de ese galpón.
        if ($warehouseId && Schema::hasTable('roles')) {
            DB::table('roles')->whereNull('warehouse_id')->where('is_system', false)->update(['warehouse_id' => $warehouseId]);
        }
    }

    /**
     * Deshacer (sólo para desarrollo y tests): vuelve los únicos a ser de todo el sistema y quita las columnas
     * agregadas. Si dos galpones repiten un código, fallará al recrear el índice único: es lo correcto.
     */
    public function down(): void
    {
        foreach (array_merge(Tenancy::TABLES, Tenancy::SHARED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if (! $index['unique'] || ! str_contains($index['name'], '_wh_')) {
                    continue;
                }
                $columns = array_values(array_diff($index['columns'], ['warehouse_id']));
                $plain = substr($table.'_'.implode('_', $columns).'_idx', 0, 60);
                $hasPlain = in_array($plain, array_column($indexes, 'name'), true);
                Schema::table($table, function (Blueprint $t) use ($index, $columns, $plain, $hasPlain) {
                    $t->dropUnique($index['name']);
                    $t->unique($columns);
                    if ($hasPlain) {
                        $t->dropIndex($plain);
                    }
                });
            }
            if (! in_array($table, Tenancy::LEGACY_TABLES, true) && Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['warehouse_id']);
                    $t->dropColumn('warehouse_id');
                });
            }
        }
        if (Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropIndex(['warehouse_id']);
                $t->dropColumn('warehouse_id');
            });
        }
    }

    private function firstWarehouseId(): ?int
    {
        $id = DB::table('warehouses')->orderBy('id')->value('id');
        if ($id) {
            return (int) $id;
        }
        if (! DB::table('users')->exists()) {
            return null; // instalación nueva: el instalador crea el primer galpón
        }
        $company = DB::table('companies')->orderBy('id')->value('id')
            ?? DB::table('companies')->insertGetId(['name' => 'Mi Empresa', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('warehouses')->insertGetId([
            'company_id' => $company, 'code' => 'GAL-A', 'name' => 'Galpón A', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Cada índice único pasa a ser único dentro del galpón: (warehouse_id, columnas…). */
    private function scopeUniqueIndexes(string $table): void
    {
        $indexes = Schema::getIndexes($table);
        $existing = array_column($indexes, 'name');
        $plainColumns = array_map(fn ($i) => $i['columns'], array_filter($indexes, fn ($i) => ! $i['unique']));
        foreach ($indexes as $index) {
            if (! $index['unique'] || $index['primary'] || in_array('warehouse_id', $index['columns'], true)) {
                continue;
            }
            $columns = $index['columns'];
            $scoped = substr($table.'_wh_'.implode('_', $columns).'_unique', 0, 60);
            $plain = substr($table.'_'.implode('_', $columns).'_idx', 0, 60);
            $needsPlain = ! in_array($plain, $existing, true) && ! in_array($columns, $plainColumns, true);
            Schema::table($table, function (Blueprint $t) use ($index, $columns, $scoped, $plain, $needsPlain) {
                // Índice común sobre las mismas columnas antes de soltar el único (MySQL lo exige si son claves foráneas).
                if ($needsPlain) {
                    $t->index($columns, $plain);
                }
                $t->dropUnique($index['name']);
                $t->unique(array_merge(['warehouse_id'], $columns), $scoped);
            });
        }
    }
};
