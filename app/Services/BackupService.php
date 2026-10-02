<?php

namespace App\Services;

use App\Events\BackupCompleted;
use App\Exceptions\BusinessException;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backups de la base de datos.
 *
 * - MySQL/MariaDB: mysqldump --single-transaction --routines --triggers, comprimido con gzip.
 *   La contraseña se pasa en un archivo de opciones temporal (--defaults-extra-file) con
 *   permisos restringidos: NUNCA en la línea de comandos ni en los logs.
 * - SQLite: copia comprimida del archivo (o volcado SQL si la base está en memoria).
 *
 * Los archivos se guardan en storage/app/private/backups con checksum SHA-256 y se
 * verifican al terminar. "Nunca asumir que la base está a salvo".
 */
class BackupService
{
    public const DIRECTORY = 'backups';

    public const TYPES = [
        'manual' => 'Manual',
        'daily' => 'Diario',
        'weekly' => 'Semanal',
        'restore' => 'Previo a restauración',
    ];

    public const STATUSES = [
        'running' => 'En curso',
        'success' => 'Correcto',
        'failed' => 'Falló',
        'pruned' => 'Archivo eliminado (retención)',
    ];

    /** Días máximos sin un backup exitoso antes de mostrar advertencia. */
    public const MAX_AGE_DAYS = 2;

    private ?string $connection = null;

    public function __construct(private readonly AuditService $audit)
    {
    }

    /** Permite respaldar una conexión distinta de la predeterminada (tests, multi-base). */
    public function usingConnection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function connectionName(): string
    {
        return $this->connection ?? (string) config('database.default');
    }

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public function pathFor(Backup $backup): string
    {
        return $this->disk()->path(self::DIRECTORY.'/'.basename($backup->filename));
    }

    public function exists(Backup $backup): bool
    {
        return $this->disk()->exists(self::DIRECTORY.'/'.basename($backup->filename));
    }

    // ------------------------------------------------------------------
    // Crear backup
    // ------------------------------------------------------------------

    public function run(string $type = 'manual', ?User $by = null): Backup
    {
        $type = array_key_exists($type, self::TYPES) ? $type : 'manual';

        $lock = Cache::lock('galpon:backup:running', 3600);
        if (! $lock->get()) {
            throw new BusinessException('Ya hay un backup en curso. Esperá a que termine.');
        }

        try {
            return $this->performBackup($type, $by);
        } finally {
            $lock->release();
        }
    }

    private function performBackup(string $type, ?User $by): Backup
    {
        @set_time_limit(0);
        $this->disk()->makeDirectory(self::DIRECTORY);

        $connection = $this->connectionName();
        $filename = 'galpon-'.$type.'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).$this->extensionFor($connection);

        $backup = Backup::query()->create([
            'filename' => $filename,
            'disk' => 'local',
            'type' => $type,
            'status' => 'running',
            'created_by' => $by?->getKey(),
            'started_at' => now(),
        ]);

        $path = $this->pathFor($backup);

        try {
            $this->dumpTo($path, $connection);
            clearstatcache(true, $path);

            $backup->forceFill([
                'status' => 'success',
                'size' => (int) filesize($path),
                'checksum' => hash_file('sha256', $path),
                'finished_at' => now(),
                'error' => null,
            ])->save();

            // Verificación inmediata: el archivo se puede leer y descomprimir. Si falla, el backup NO cuenta como válido.
            $check = $this->verify($backup);
            if (! $check['ok']) {
                throw new RuntimeException('El backup no pasó la verificación: '.$check['message']);
            }
        } catch (Throwable $e) {
            if (is_file($path)) {
                @unlink($path);
            }
            $backup->forceFill([
                'status' => 'failed',
                'error' => Str::limit($this->sanitizeError($e->getMessage()), 1000),
                'finished_at' => now(),
            ])->save();
            Log::error('Backup '.$backup->filename.' falló: '.$backup->error);
        }

        $this->audit->log(
            'backup',
            null,
            null,
            ['backup_id' => $backup->id, 'filename' => $backup->filename, 'type' => $type, 'status' => $backup->status],
            ($backup->status === 'success' ? 'Backup '.$type.' generado' : 'Backup '.$type.' fallido')
                .($by ? ' por '.$by->full_name : ''),
        );

        if ($backup->status === 'success') {
            try {
                $this->applyRetention();
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            event(new BackupCompleted($backup));
        } catch (Throwable $e) {
            report($e);
        }

        return $backup;
    }

    /** Genera el volcado de la conexión indicada en $target (archivo .gz). */
    public function dumpTo(string $target, ?string $connection = null): void
    {
        $connection ??= $this->connectionName();
        $config = config('database.connections.'.$connection);
        if (! is_array($config)) {
            throw new RuntimeException('Conexión de base de datos desconocida: '.$connection);
        }

        match ($config['driver'] ?? null) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $target),
            'sqlite' => $this->isMemorySqlite($config)
                ? $this->dumpSqliteSql($connection, $target)
                : $this->compressFile((string) $config['database'], $target),
            default => throw new RuntimeException('Motor de base de datos no soportado para backups: '.($config['driver'] ?? '?')),
        };

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('El archivo de backup quedó vacío.');
        }
    }

    private function extensionFor(string $connection): string
    {
        $config = (array) config('database.connections.'.$connection, []);

        return ($config['driver'] ?? null) === 'sqlite' && ! $this->isMemorySqlite($config) ? '.sqlite.gz' : '.sql.gz';
    }

    private function isMemorySqlite(array $config): bool
    {
        $db = (string) ($config['database'] ?? '');

        return $db === ':memory:' || str_contains($db, 'mode=memory');
    }

    private function dumpMysql(array $config, string $target): void
    {
        $binary = $this->binary('mysqldump');
        $optionsFile = $this->writeOptionsFile($config);
        $gz = null;

        try {
            $gz = gzopen($target, 'wb6');
            if ($gz === false) {
                throw new RuntimeException('No se pudo crear el archivo de backup.');
            }

            $process = new Process([
                $binary,
                '--defaults-extra-file='.$optionsFile, // debe ser la primera opción
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                (string) $config['database'],
            ], base_path(), $this->processEnv(), null, 3600);

            $stderr = '';
            $process->run(function (string $type, string $buffer) use ($gz, &$stderr) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                } elseif (strlen($stderr) < 4000) {
                    $stderr .= $buffer;
                }
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump terminó con código '.$process->getExitCode().': '.Str::limit(trim($stderr), 600));
            }
        } finally {
            if (is_resource($gz)) {
                gzclose($gz);
            }
            @unlink($optionsFile);
        }
    }

    private function compressFile(string $source, string $target): void
    {
        if (! is_file($source)) {
            throw new RuntimeException('No se encontró el archivo de la base SQLite.');
        }

        $in = fopen($source, 'rb');
        $gz = gzopen($target, 'wb6');
        try {
            while (! feof($in)) {
                gzwrite($gz, (string) fread($in, 1024 * 512));
            }
        } finally {
            fclose($in);
            gzclose($gz);
        }
    }

    /** Volcado SQL de una base SQLite (útil para bases en memoria, p.ej. en tests). */
    private function dumpSqliteSql(string $connection, string $target): void
    {
        $pdo = DB::connection($connection)->getPdo();
        $gz = gzopen($target, 'wb6');

        try {
            gzwrite($gz, "-- Galpon SQLite dump\n-- Generado: ".now()->toIso8601String()."\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");

            $objects = $pdo->query("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END, name")
                ->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($objects as $object) {
                if ($object['type'] !== 'table') {
                    gzwrite($gz, $object['sql'].";\n");
                    continue;
                }

                $table = str_replace('"', '""', $object['name']);
                gzwrite($gz, 'DROP TABLE IF EXISTS "'.$table."\";\n".$object['sql'].";\n");

                $rows = $pdo->query('SELECT * FROM "'.$table.'"');
                while (($row = $rows->fetch(\PDO::FETCH_NUM)) !== false) {
                    $values = array_map(fn ($v) => match (true) {
                        $v === null => 'NULL',
                        is_int($v), is_float($v) => (string) $v,
                        str_contains((string) $v, "\0") => "X'".bin2hex((string) $v)."'",
                        default => $pdo->quote((string) $v),
                    }, $row);
                    gzwrite($gz, 'INSERT INTO "'.$table.'" VALUES('.implode(',', $values).");\n");
                }
            }

            gzwrite($gz, "COMMIT;\nPRAGMA foreign_keys=ON;\n");
        } finally {
            gzclose($gz);
        }
    }

    // ------------------------------------------------------------------
    // Verificación
    // ------------------------------------------------------------------

    /** @return array{ok: bool, message: string} */
    public function verify(Backup $backup): array
    {
        $result = $this->check($backup);

        $backup->forceFill($result['ok']
            ? ['verified_at' => now(), 'error' => null]
            : ['verified_at' => null, 'error' => Str::limit('Verificación: '.$result['message'], 1000)]
        )->save();

        return $result;
    }

    /** @return array{ok: bool, message: string} */
    private function check(Backup $backup): array
    {
        if ($backup->status !== 'success') {
            return ['ok' => false, 'message' => 'El backup no terminó correctamente.'];
        }

        $path = $this->pathFor($backup);
        if (! is_file($path)) {
            return ['ok' => false, 'message' => 'El archivo no existe en el servidor.'];
        }
        if ($backup->checksum && ! hash_equals($backup->checksum, (string) hash_file('sha256', $path))) {
            return ['ok' => false, 'message' => 'El checksum SHA-256 no coincide: el archivo fue modificado o está dañado.'];
        }

        $gz = @gzopen($path, 'rb');
        if ($gz === false) {
            return ['ok' => false, 'message' => 'No se pudo abrir el archivo comprimido.'];
        }

        $head = '';
        $tail = '';
        $bytes = 0;
        try {
            while (! gzeof($gz)) {
                $chunk = @gzread($gz, 1024 * 512);
                if ($chunk === false) {
                    return ['ok' => false, 'message' => 'El archivo comprimido está dañado.'];
                }
                if ($chunk === '') {
                    break;
                }
                if (strlen($head) < 4096) {
                    $head .= substr($chunk, 0, 4096 - strlen($head));
                }
                $tail = substr($tail.$chunk, -512);
                $bytes += strlen($chunk);
            }
        } finally {
            gzclose($gz);
        }

        if ($bytes === 0) {
            return ['ok' => false, 'message' => 'El archivo está vacío.'];
        }

        if (str_ends_with($backup->filename, '.sqlite.gz')) {
            return str_starts_with($head, "SQLite format 3\0")
                ? ['ok' => true, 'message' => 'Archivo SQLite válido ('.$this->humanSize($bytes).' sin comprimir).']
                : ['ok' => false, 'message' => 'El archivo no tiene el encabezado de una base SQLite.'];
        }

        $isMysql = str_contains($head, '-- MySQL dump') || str_contains($head, '-- MariaDB dump');
        $isSqlite = str_contains($head, '-- Galpon SQLite dump');
        if (! $isMysql && ! $isSqlite) {
            return ['ok' => false, 'message' => 'El encabezado del volcado SQL no es reconocible.'];
        }
        if ($isMysql && ! str_contains($tail, '-- Dump completed')) {
            return ['ok' => false, 'message' => 'El volcado está incompleto (falta la marca de finalización de mysqldump).'];
        }
        if ($isSqlite && ! str_contains($tail, 'COMMIT;')) {
            return ['ok' => false, 'message' => 'El volcado SQLite está incompleto.'];
        }

        return ['ok' => true, 'message' => 'Volcado SQL válido ('.$this->humanSize($bytes).' sin comprimir).'];
    }

    // ------------------------------------------------------------------
    // Restauración
    // ------------------------------------------------------------------

    /**
     * Restaura un backup. Antes crea un backup previo automático y pone el sistema en
     * modo mantenimiento mientras dura la restauración. Devuelve el backup previo.
     */
    public function restore(Backup $backup, User $by, ?string $reason = null): Backup
    {
        $lock = Cache::lock('galpon:backup:restore', 3600);
        if (! $lock->get()) {
            throw new BusinessException('Ya hay una restauración en curso.');
        }

        try {
            @set_time_limit(0);

            $verification = $this->verify($backup);
            if (! $verification['ok']) {
                throw new BusinessException('El backup no pasó la verificación y no se puede restaurar: '.$verification['message']);
            }

            $pre = $this->run('restore', $by);
            if ($pre->status !== 'success') {
                throw new BusinessException('No se pudo crear el backup previo a la restauración. No se modificó nada.');
            }

            $preAttributes = $pre->only(['filename', 'disk', 'size', 'type', 'status', 'checksum', 'verified_at', 'error', 'created_by', 'started_at', 'finished_at']);
            $sourceAttributes = $backup->only(['filename', 'disk', 'size', 'type', 'status', 'checksum', 'verified_at', 'error', 'created_by', 'started_at', 'finished_at']);

            $wasDown = app()->isDownForMaintenance();
            if (! $wasDown) {
                Artisan::call('down', ['--retry' => 60]);
            }

            try {
                $this->restoreInto($this->pathFor($backup), $backup->filename, $this->connectionName());
            } finally {
                if (! $wasDown) {
                    Artisan::call('up');
                }
            }

            // La base restaurada no conoce el backup previo: se vuelve a registrar.
            foreach ([$preAttributes, $sourceAttributes] as $attributes) {
                Backup::query()->firstOrCreate(['filename' => $attributes['filename']], $attributes);
            }

            app(SettingsService::class)->flush();
            app(ModuleService::class)->flush();

            $this->audit->log(
                'restore_backup',
                null,
                null,
                ['filename' => $backup->filename, 'pre_restore_backup' => $pre->filename],
                'Restauró el backup '.$backup->filename.' ('.$by->full_name.')',
                $reason,
            );
            Log::warning('Base de datos restaurada desde '.$backup->filename.' por '.$by->username.'. Backup previo: '.$pre->filename);

            return Backup::query()->where('filename', $pre->filename)->first() ?? $pre;
        } finally {
            $lock->release();
        }
    }

    private function restoreInto(string $path, string $filename, string $connection): void
    {
        $config = (array) config('database.connections.'.$connection, []);

        match ($config['driver'] ?? null) {
            'mysql', 'mariadb' => $this->restoreMysql($config, $path),
            'sqlite' => str_ends_with($filename, '.sqlite.gz')
                ? $this->restoreSqliteFile($config, $connection, $path)
                : $this->restoreSqlDump($connection, $path),
            default => throw new BusinessException('Motor de base de datos no soportado para restaurar.'),
        };

        // Reconecta para no usar estado viejo (una base SQLite en memoria se perdería al reconectar).
        if (! $this->isMemorySqlite($config)) {
            DB::purge($connection);
        }
    }

    private function restoreMysql(array $config, string $path): void
    {
        $binary = $this->binary('mysql');
        $optionsFile = $this->writeOptionsFile($config);
        $input = fopen('compress.zlib://'.$path, 'rb');

        try {
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$optionsFile,
                '--default-character-set=utf8mb4',
                (string) $config['database'],
            ], base_path(), $this->processEnv(), $input, 3600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysql terminó con código '.$process->getExitCode().': '
                    .Str::limit($this->sanitizeError(trim($process->getErrorOutput())), 600));
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            @unlink($optionsFile);
        }
    }

    private function restoreSqliteFile(array $config, string $connection, string $path): void
    {
        $database = (string) ($config['database'] ?? '');
        if ($this->isMemorySqlite($config) || $database === '') {
            throw new BusinessException('No se puede restaurar un archivo SQLite sobre una base en memoria.');
        }

        $temp = $database.'.restoring';
        $in = gzopen($path, 'rb');
        $out = fopen($temp, 'wb');
        try {
            while (! gzeof($in)) {
                fwrite($out, (string) gzread($in, 1024 * 512));
            }
        } finally {
            gzclose($in);
            fclose($out);
        }

        DB::disconnect($connection);
        if (! @rename($temp, $database)) {
            copy($temp, $database);
            @unlink($temp);
        }
    }

    private function restoreSqlDump(string $connection, string $path): void
    {
        $sql = (string) file_get_contents('compress.zlib://'.$path);
        DB::connection($connection)->unprepared($sql);
    }

    // ------------------------------------------------------------------
    // Retención y estado
    // ------------------------------------------------------------------

    /** Borra archivos más viejos que backup.retention_days (conserva el registro y siempre el último exitoso). */
    public function applyRetention(): int
    {
        $days = (int) setting('backup.retention_days', 30);
        if ($days <= 0) {
            return 0;
        }

        $keepId = $this->lastSuccessful()?->id;
        $count = 0;

        Backup::query()->where('status', 'success')
            ->where('finished_at', '<', now()->subDays($days))
            ->when($keepId, fn ($q) => $q->where('id', '!=', $keepId))
            ->orderBy('id')
            ->each(function (Backup $backup) use (&$count, $days) {
                $this->disk()->delete(self::DIRECTORY.'/'.basename($backup->filename));
                $backup->forceFill([
                    'status' => 'pruned',
                    'error' => 'Archivo eliminado automáticamente por la política de retención ('.$days.' días).',
                ])->save();
                $count++;
            });

        return $count;
    }

    public function lastSuccessful(): ?Backup
    {
        return Backup::query()->where('status', 'success')->whereNotNull('verified_at')->latest('finished_at')->latest('id')->first();
    }

    /**
     * Estado general para mostrar en pantallas: último backup y advertencias.
     *
     * @return array{last: ?Backup, lastAttempt: ?Backup, warnings: list<string>}
     */
    public function health(): array
    {
        $last = $this->lastSuccessful();
        $lastAttempt = Backup::query()->latest('started_at')->latest('id')->first();
        $warnings = [];

        if (! $last) {
            $warnings[] = 'No hay ningún backup exitoso. Generá uno ahora.';
        } else {
            if ($last->finished_at && $last->finished_at->lt(now()->subDays(self::MAX_AGE_DAYS))) {
                $warnings[] = 'El último backup exitoso tiene más de '.self::MAX_AGE_DAYS.' días ('.fdate($last->finished_at, true).').';
            }
            if ($last->verified_at === null) {
                $warnings[] = 'El último backup nunca se verificó.';
            }
        }
        if ($lastAttempt && $lastAttempt->status === 'failed') {
            $warnings[] = 'El último intento de backup falló: '.Str::limit((string) $lastAttempt->error, 160);
        }

        return ['last' => $last, 'lastAttempt' => $lastAttempt, 'warnings' => $warnings];
    }

    public function humanSize(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return num($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return num($bytes, 1).' GB';
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** Ubica mysqldump/mysql: GALPON_MYSQL_BIN, PATH o la carpeta típica de XAMPP. */
    public function binary(string $name): string
    {
        $dir = trim((string) config('galpon.mysql_bin_path', ''));
        if ($dir !== '') {
            foreach ([$name.'.exe', $name] as $candidate) {
                $path = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.$candidate;
                if (is_file($path)) {
                    return $path;
                }
            }
            throw new RuntimeException('No se encontró '.$name.' en la carpeta configurada en GALPON_MYSQL_BIN.');
        }

        $found = (new ExecutableFinder)->find($name, null, ['C:\\xampp\\mysql\\bin', '/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin']);
        if (! $found) {
            throw new RuntimeException('No se encontró '.$name.'. Configurá GALPON_MYSQL_BIN en .env (en XAMPP: C:\\xampp\\mysql\\bin).');
        }

        return $found;
    }

    /**
     * Variables de entorno para mysqldump/mysql. En Windows, si el proceso web no tiene
     * SystemRoot (p.ej. `php artisan serve` filtra el entorno), Winsock no inicia y el cliente
     * falla con "Can't create TCP/IP socket (10106)".
     */
    private function processEnv(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $root = getenv('SystemRoot') ?: getenv('windir') ?: 'C:\\Windows';

        return ['SystemRoot' => $root, 'windir' => $root];
    }

    /**
     * Archivo de opciones temporal con las credenciales (permisos 0600). Se borra siempre
     * al terminar. Así la contraseña no aparece en la lista de procesos ni en los logs.
     */
    private function writeOptionsFile(array $config): string
    {
        $file = tempnam(sys_get_temp_dir(), 'gbk');
        if ($file === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de credenciales.');
        }
        @chmod($file, 0600);

        $quote = fn ($value) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value).'"';
        $lines = ['[client]', 'user='.$quote($config['username'] ?? 'root'), 'password='.$quote($config['password'] ?? '')];
        if (! empty($config['unix_socket'])) {
            $lines[] = 'socket='.$quote($config['unix_socket']);
        } else {
            $lines[] = 'host='.$quote($config['host'] ?? '127.0.0.1');
            $lines[] = 'port='.(int) ($config['port'] ?? 3306);
        }

        file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL);

        return $file;
    }

    /** Quita de un mensaje de error cualquier rastro de la contraseña configurada. */
    private function sanitizeError(string $message): string
    {
        foreach (config('database.connections', []) as $connection) {
            $password = (string) ($connection['password'] ?? '');
            if ($password !== '' && strlen($password) >= 3) {
                $message = str_replace($password, '********', $message);
            }
        }

        return $message;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type] ?? (string) $type;
    }

    public static function lastSuccessfulAt(): ?Carbon
    {
        $value = Backup::query()->where('status', 'success')->max('finished_at');

        return $value ? Carbon::parse($value) : null;
    }
}
