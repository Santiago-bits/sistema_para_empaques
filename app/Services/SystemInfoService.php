<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Información técnica del sistema para la pantalla de soporte, el panel del
 * desarrollador y el comando galpon:status. Nunca expone credenciales.
 */
class SystemInfoService
{
    public const SCHEDULER_CACHE_KEY = 'galpon:scheduler:last_run';

    /** Tablas principales cuyo tamaño interesa vigilar. */
    public const MAIN_TABLES = [
        'crates', 'pallets', 'production_records', 'loads', 'load_crates', 'remitos', 'invoices', 'audit_logs',
        'state_histories', 'location_movements', 'temperature_records', 'notifications', 'system_errors', 'sessions',
        'jobs', 'failed_jobs', 'cache',
    ];

    public function __construct(private readonly BackupService $backups, private readonly ModuleService $modules)
    {
    }

    public function version(): string
    {
        return (string) config('galpon.version');
    }

    /** @return array{driver: string, version: ?string, database: ?string, ok: bool, error: ?string} */
    public function database(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        try {
            $version = match ($driver) {
                'sqlite' => 'SQLite '.$connection->selectOne('select sqlite_version() as v')->v,
                'mysql', 'mariadb' => (string) $connection->selectOne('select version() as v')->v,
                default => null,
            };

            return ['driver' => $driver, 'version' => $version, 'database' => $this->databaseLabel(), 'ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['driver' => $driver, 'version' => null, 'database' => $this->databaseLabel(), 'ok' => false, 'error' => class_basename($e)];
        }
    }

    /** Fecha de la última actualización (última migración aplicada, según el prefijo de su nombre). */
    public function lastUpdate(): ?array
    {
        try {
            $row = DB::table('migrations')->orderByDesc('batch')->orderByDesc('id')->first(['migration', 'batch']);
        } catch (Throwable) {
            return null;
        }
        if (! $row) {
            return null;
        }

        $date = null;
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{2})(\d{2})(\d{2})/', $row->migration, $m)) {
            try {
                $date = Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5], (int) $m[6]);
            } catch (Throwable) {
                $date = null;
            }
        }

        return ['migration' => $row->migration, 'batch' => (int) $row->batch, 'date' => $date];
    }

    /** @return array<string, string> */
    public function server(): array
    {
        $db = $this->database();

        return [
            'PHP' => PHP_VERSION.' ('.PHP_SAPI.')',
            'Base de datos' => trim(($db['version'] ?? $db['driver'])),
            'Sistema operativo' => PHP_OS_FAMILY.' '.php_uname('r'),
            'Servidor web' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? (app()->runningInConsole() ? 'consola' : '—')),
            'Laravel' => app()->version(),
            'Zona horaria' => (string) config('app.timezone'),
        ];
    }

    /** @return array{status: string, label: string, checks: array<string, bool>} */
    public function health(): array
    {
        $db = $this->database();
        $backup = $this->backups->health();
        $checks = [
            'database' => $db['ok'],
            'backup' => $backup['warnings'] === [],
            'storage_writable' => is_writable(storage_path('app')),
        ];
        $critical = $this->openAlerts('critical');

        $status = ! $checks['database'] ? 'error' : (in_array(false, $checks, true) || $critical > 0 ? 'warning' : 'ok');

        return [
            'status' => $status,
            'label' => ['ok' => 'Operativo', 'warning' => 'Operativo con advertencias', 'error' => 'Con fallas'][$status],
            'checks' => $checks,
        ];
    }

    public function openAlerts(?string $severity = null): int
    {
        try {
            return Alert::query()->open()->when($severity, fn ($q) => $q->where('severity', $severity))->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array{connection: string, pending: ?int, failed: ?int} */
    public function queue(): array
    {
        $count = function (string $table): ?int {
            try {
                return Schema::hasTable($table) ? DB::table($table)->count() : null;
            } catch (Throwable) {
                return null;
            }
        };

        return ['connection' => (string) config('queue.default'), 'pending' => $count('jobs'), 'failed' => $count('failed_jobs')];
    }

    /** @return array{free: ?float, total: ?float, used_pct: ?float} */
    public function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'free' => $free === false ? null : (float) $free,
            'total' => $total === false ? null : (float) $total,
            'used_pct' => ($free !== false && $total) ? round(100 - ($free / $total * 100), 1) : null,
        ];
    }

    /** @return list<array{table: string, rows: ?int, bytes: ?int}> */
    public function tableSizes(): array
    {
        $driver = DB::connection()->getDriverName();

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                return DB::table('information_schema.TABLES')
                    ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                    ->orderByRaw('(DATA_LENGTH + INDEX_LENGTH) desc')
                    ->limit(20)
                    ->get(['TABLE_NAME as name', 'TABLE_ROWS as rows', DB::raw('(DATA_LENGTH + INDEX_LENGTH) as bytes')])
                    ->map(fn ($t) => ['table' => $t->name, 'rows' => (int) $t->rows, 'bytes' => (int) $t->bytes])
                    ->all();
            }

            return collect(self::MAIN_TABLES)
                ->filter(fn ($t) => Schema::hasTable($t))
                ->map(fn ($t) => ['table' => $t, 'rows' => DB::table($t)->count(), 'bytes' => null])
                ->sortByDesc('rows')->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, array{name: string, enabled: bool}> */
    public function modules(): array
    {
        return collect(ModuleService::CATALOG)
            ->map(fn ($m, $key) => ['name' => $m[0], 'enabled' => $this->modules->enabled($key)])
            ->all();
    }

    public function schedulerLastRun(): ?Carbon
    {
        try {
            $value = Cache::get(self::SCHEDULER_CACHE_KEY);

            return $value ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    public function environment(): array
    {
        return [
            'Entorno' => (string) app()->environment(),
            'Modo debug' => config('app.debug') ? 'Activado (no usar en producción)' : 'Desactivado',
            'URL' => (string) config('app.url'),
            'Instalación' => (string) config('galpon.installation_id'),
            'Cache' => (string) config('cache.default'),
            'Sesiones' => (string) config('session.driver'),
            'Colas' => (string) config('queue.default'),
            'Logs' => (string) config('logging.default'),
            'Mantenimiento' => app()->isDownForMaintenance() ? 'Sí' : 'No',
        ];
    }

    /** @return array<string, string> */
    public function phpSettings(): array
    {
        return collect(['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size', 'date.timezone', 'opcache.enable'])
            ->mapWithKeys(fn ($k) => [$k => (string) (ini_get($k) === false ? '—' : ini_get($k))])
            ->all();
    }

    /** @return list<string> */
    public function extensions(): array
    {
        $list = get_loaded_extensions();
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /** Extensiones necesarias que faltan (QR y códigos se generan en SVG y ARCA usa HTTP: no requieren gd ni soap). */
    public function missingExtensions(): array
    {
        return array_values(array_filter(
            ['pdo_mysql', 'mbstring', 'openssl', 'zip', 'zlib', 'fileinfo', 'curl'],
            fn ($ext) => ! extension_loaded($ext),
        ));
    }

    private function databaseLabel(): ?string
    {
        try {
            $name = DB::connection()->getDatabaseName();

            return DB::connection()->getDriverName() === 'sqlite' ? basename((string) $name) : $name;
        } catch (Throwable) {
            return null;
        }
    }
}
