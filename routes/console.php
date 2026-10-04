<?php

use App\Services\Central\CentralSyncService;
use App\Services\ExchangeRateService;
use App\Services\SystemInfoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| En el servidor del galpón basta con una tarea de Windows que ejecute cada minuto:
|   php artisan schedule:run
| (ver docs/INSTALACION.md). En Hostinger, un Cron Job con el mismo comando (docs/HOSTINGER.md).
|
| IMPORTANTE: los comandos se ejecutan DENTRO del mismo proceso (Schedule::call + Artisan::call) y no con
| Schedule::command, que abre un proceso nuevo con proc_open: Hostinger y muchos hostings compartidos
| tienen proc_open deshabilitado y ninguna tarea correría. Todas evitan superponerse si una corrida se demora.
*/

/** Programa un comando de artisan sin abrir procesos nuevos. */
$artisan = fn (string $command) => Schedule::call(fn () => Artisan::call($command))->name($command);

// Señal de vida del programador (el panel de desarrollador avisa si dejó de correr).
Schedule::call(fn () => Cache::put(SystemInfoService::SCHEDULER_CACHE_KEY, now()->toIso8601String(), now()->addDay()))
    ->everyMinute()->name('scheduler-heartbeat');

// Cola de trabajos (exportaciones grandes, avisos por WhatsApp): sin servicio aparte en Windows/XAMPP.
$artisan('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

// Alertas: stock bajo, cargas pendientes, temperatura, vencimientos, mantenimiento, etc.
$artisan('galpon:check-alerts')->everyFiveMinutes()->withoutOverlapping(10);

// Backups automáticos (se pueden apagar en Configuración → Backups). Sin proc_open (hosting compartido)
// el volcado se hace en PHP, sin mysqldump.
$artisan('galpon:backup --type=daily')->dailyAt('02:00')->withoutOverlapping(120)
    ->when(fn () => (bool) setting('backup.daily', true));
$artisan('galpon:backup --type=weekly')->weeklyOn(0, '03:00')->withoutOverlapping(120)
    ->when(fn () => (bool) setting('backup.weekly', true));

// Panel General del proveedor: uso, soporte y licencia (sólo si GALPON_CENTRAL_URL está configurado).
$artisan('galpon:central-sync')->everyFiveMinutes()->withoutOverlapping(10)
    ->when(fn () => app(CentralSyncService::class)->enabled());

// Limpieza: enlaces de recuperación de contraseña vencidos y trabajos fallidos viejos.
$artisan('auth:clear-resets')->daily();
$artisan('queue:prune-failed --hours=720')->daily();

// Valor del dólar: si está activada la actualización automática (Tesorería → Valor del dólar), lo trae cada 6 horas.
Schedule::call(fn () => app(ExchangeRateService::class)->autoUpdate())->hourly()->name('valor-dolar')->withoutOverlapping(10)
    ->when(fn () => ExchangeRateService::autoEnabled());
