<?php

namespace App\Services;

use Database\Seeders\SystemSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pone la base de datos al día después de cada actualización del sistema, sin consola: en el hosting compartido
 * (Hostinger) el deploy por Git sólo copia el código y no corre las migraciones, así que las pantallas nuevas
 * fallaban por tablas o columnas que todavía no existían.
 *
 * - Marca (storage/framework/schema.ok) con la lista de migraciones, los permisos/módulos y la base: si coincide,
 *   no se consulta nada.
 * - Si cambió algo: copia de seguridad previa (si hay migraciones pendientes), `migrate` y sincronización de
 *   permisos, roles y módulos nuevos (SystemSeeder, que no pisa lo configurado). De a una petición por vez.
 * - Si falla, se guarda el error (storage/framework/upgrade.json), no se reintenta solo por 10 minutos y el super
 *   admin lo ve y puede reintentar desde la administración general.
 */
class DatabaseUpgrader
{
    private const FIRST_MIGRATION = '2026_01_01_000100_create_core_tables';

    public const RETRY_MINUTES = 10;

    public function isUpToDate(): bool
    {
        return trim((string) @file_get_contents($this->markerPath())) === $this->signature();
    }

    /** ¿Ya pasó por el instalador? (si no, la base la arma el instalador, no esto) */
    public function isInstalled(): bool
    {
        return Schema::hasTable('migrations') && DB::table('migrations')->where('migration', self::FIRST_MIGRATION)->exists();
    }

    /** Migraciones del código que la base todavía no tiene. Vacío si el sistema no está instalado. */
    public function pending(): array
    {
        if (! $this->isInstalled()) {
            return [];
        }
        $ran = DB::table('migrations')->pluck('migration')->all();

        return array_values(array_diff($this->files(), $ran));
    }

    public function markUpToDate(): void
    {
        @file_put_contents($this->markerPath(), $this->signature());
    }

    public function failedRecently(): bool
    {
        $last = $this->lastResult();

        return $last !== null && ! $last['ok'] && (time() - (int) ($last['at'] ?? 0)) < self::RETRY_MINUTES * 60;
    }

    /** @return array{ok: bool, at: int, migrated?: list<string>, error?: string}|null */
    public function lastResult(): ?array
    {
        $data = json_decode((string) @file_get_contents($this->resultPath()), true);

        return is_array($data) ? $data : null;
    }

    /** 'done' | 'busy' (otra petición ya está actualizando) | 'failed' */
    public function run(bool $withBackup = true): string
    {
        $lock = @fopen(storage_path('framework/upgrade.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return 'busy';
        }

        try {
            if (! $this->isInstalled()) {
                return 'done';
            }
            $pending = $this->pending();
            if ($pending !== []) {
                if ($withBackup && ! app()->runningUnitTests()) {
                    try {
                        app(BackupService::class)->run('upgrade');
                    } catch (Throwable $e) {
                        report($e); // la copia es una precaución: si no se puede, se actualiza igual
                    }
                }
                @set_time_limit(300);
                Artisan::call('migrate', ['--force' => true]);
            }
            // Permisos, roles y módulos nuevos de esta versión (no pisa lo que el galpón configuró).
            (new SystemSeeder)->run();
            app(ModuleService::class)->flush();

            $this->markUpToDate();
            $this->saveResult(['ok' => true, 'at' => time(), 'migrated' => $pending]);
            if ($pending !== []) {
                app(AuditService::class)->log('settings', null, null, ['migrated' => $pending], 'Base de datos actualizada a la versión '.config('galpon.version'));
            }

            return 'done';
        } catch (Throwable $e) {
            report($e);
            $this->saveResult(['ok' => false, 'at' => time(), 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return 'failed';
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function files(): array
    {
        return collect(glob(database_path('migrations/*.php')) ?: [])->map(fn ($f) => basename($f, '.php'))->sort()->values()->all();
    }

    /** Cambia si cambian las migraciones, los permisos/sectores/módulos o la base configurada. */
    private function signature(): string
    {
        $default = (string) config('database.default');
        $catalogs = implode(',', array_map(fn ($f) => (string) @md5_file($f), [
            config_path('permissions.php'), config_path('sectors.php'), app_path('Services/ModuleService.php'), database_path('seeders/SystemSeeder.php'),
        ]));

        return sha1($default.'|'.config("database.connections.{$default}.host").'|'.config("database.connections.{$default}.database")
            .'|'.implode(',', $this->files()).'|'.$catalogs);
    }

    private function saveResult(array $result): void
    {
        @file_put_contents($this->resultPath(), json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    private function markerPath(): string
    {
        return storage_path('framework/schema.ok');
    }

    private function resultPath(): string
    {
        return storage_path('framework/upgrade.json');
    }
}
