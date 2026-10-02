<?php

namespace App\Console\Commands;

use App\Services\Central\CentralSyncService;
use App\Services\SystemInfoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Revisa que el servidor quedó bien configurado (Hostinger, VPS o la PC del galpón) y dice qué corregir.
 *   php artisan galpon:deploy-check          (sin red)
 *   php artisan galpon:deploy-check --ping   (además prueba la conexión con el Panel General)
 */
class DeployCheckCommand extends Command
{
    protected $signature = 'galpon:deploy-check {--ping : Probar la conexión con el Panel General}';

    protected $description = 'Verifica la instalación (PHP, .env, base de datos, permisos, tareas programadas, Panel General)';

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> estado, control, detalle, solución */
    private array $rows = [];

    public function handle(SystemInfoService $system): int
    {
        $this->check(PHP_VERSION_ID >= 80200, 'PHP 8.2 o superior', PHP_VERSION, 'Hostinger: hPanel → Avanzado → Configuración de PHP → versión 8.2 o 8.3.');
        $missing = $system->missingExtensions();
        $this->check($missing === [], 'Extensiones de PHP', $missing ? 'Faltan: '.implode(', ', $missing) : 'Completas', 'Activarlas en la configuración de PHP del hosting.');

        $this->check(filled(config('app.key')), 'APP_KEY', filled(config('app.key')) ? 'Configurada' : 'Vacía', 'php artisan key:generate');
        $this->check(app()->environment('production'), 'APP_ENV=production', (string) app()->environment(), 'Poner APP_ENV=production en .env.', 'warn');
        $this->check(! config('app.debug'), 'APP_DEBUG=false', config('app.debug') ? 'true' : 'false', 'NUNCA dejar APP_DEBUG=true en un servidor público.');
        $url = (string) config('app.url');
        $public = ! preg_match('#^https?://(localhost|127\.|192\.168\.|10\.)#', $url);
        $this->check(! $public || str_starts_with($url, 'https://'), 'APP_URL con https', $url, 'En internet usar https (Hostinger da SSL gratis: hPanel → Seguridad → SSL).', $public ? 'error' : 'warn');

        try {
            DB::connection()->getPdo();
            $this->check(true, 'Base de datos', DB::connection()->getDatabaseName(), '');
            $pending = $this->pendingMigrations();
            $this->check($pending === 0, 'Migraciones al día', $pending ? $pending.' pendiente(s)' : 'Sí', 'php artisan migrate --force');
        } catch (Throwable $e) {
            $this->check(false, 'Base de datos', 'Sin conexión: '.mb_substr($e->getMessage(), 0, 80), 'Revisar DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD en .env.');
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $this->check(is_writable($path), 'Escritura en '.$label, is_writable($path) ? 'OK' : 'Sin permiso', 'chmod -R 775 '.$label);
        }
        $this->check(File::exists(public_path('storage')), 'Enlace public/storage', File::exists(public_path('storage')) ? 'OK' : 'Falta', 'php artisan storage:link', 'warn');
        $this->check(File::exists(public_path('build/manifest.json')), 'Interfaz compilada (public/build)', File::exists(public_path('build/manifest.json')) ? 'OK' : 'Falta', 'Subir la carpeta public/build del repositorio.');
        $this->check(app()->configurationIsCached(), 'Configuración en caché', app()->configurationIsCached() ? 'Sí' : 'No', 'php artisan config:cache && php artisan route:cache && php artisan view:cache', 'warn');

        $last = $system->schedulerLastRun();
        $this->check($last !== null && $last->gt(now()->subMinutes(5)), 'Tareas programadas (cron)', $last ? 'Última: '.$last->diffForHumans() : 'Nunca corrió',
            'Hostinger: hPanel → Avanzado → Cron Jobs → cada minuto: php …/artisan schedule:run', 'warn');
        $proc = function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
        $this->check($proc, 'proc_open (backups con mysqldump)', $proc ? 'Disponible' : 'Deshabilitado',
            'En hosting compartido usá los backups del panel del hosting.', 'warn');

        if (config('galpon.central.mode')) {
            $this->check(true, 'Modo Panel General', 'Activado', '');
        } else {
            $sync = app(CentralSyncService::class);
            $this->check($sync->enabled(), 'Conexión al Panel General', $sync->enabled() ? (string) config('galpon.central.url') : 'No configurada',
                'Opcional: GALPON_CENTRAL_URL y GALPON_LICENSE_KEY (los da el proveedor).', 'warn');
            if ($this->option('ping') && $sync->enabled()) {
                try {
                    $status = Http::timeout(10)->acceptJson()->withToken((string) config('galpon.central.key'))
                        ->withHeaders(['X-Installation-Id' => (string) config('galpon.installation_id')])
                        ->get(rtrim((string) config('galpon.central.url'), '/').'/api/central/v1/novedades')->status();
                    $this->check($status === 200, 'Respuesta del Panel General', 'HTTP '.$status,
                        $status === 401 ? 'La clave o el ID de instalación no coinciden con los del Panel General.' : 'Revisar la URL y que el Panel tenga GALPON_CENTRAL_MODE=true.');
                } catch (Throwable $e) {
                    $this->check(false, 'Respuesta del Panel General', 'Sin conexión', 'Revisar internet / la URL: '.mb_substr($e->getMessage(), 0, 60));
                }
            }
        }

        $this->table(['', 'Control', 'Estado', 'Qué hacer'], $this->rows);
        $errors = count(array_filter($this->rows, fn ($r) => $r[0] === '✗'));
        $warnings = count(array_filter($this->rows, fn ($r) => $r[0] === '!'));
        $errors === 0
            ? $this->info('Instalación correcta'.($warnings ? " ({$warnings} recomendación/es)." : '.'))
            : $this->error("{$errors} problema(s) para corregir.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(bool $ok, string $label, string $detail, string $fix, string $level = 'error'): void
    {
        $this->rows[] = [$ok ? '✓' : ($level === 'warn' ? '!' : '✗'), $label, $detail, $ok ? '' : $fix];
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return count($migrator->getMigrationFiles(database_path('migrations')));
        }
        $ran = $migrator->getRepository()->getRan();

        return count(array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran));
    }
}
