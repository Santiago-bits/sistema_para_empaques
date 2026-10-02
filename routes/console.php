<?php

use App\Services\SystemInfoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| En el servidor del galpón basta con una tarea de Windows que ejecute cada minuto:
|   php artisan schedule:run
| (ver docs/INSTALACION.md). Todas evitan superponerse si una corrida se demora.
*/

// Señal de vida del programador (el panel de desarrollador avisa si dejó de correr).
Schedule::call(fn () => Cache::put(SystemInfoService::SCHEDULER_CACHE_KEY, now()->toIso8601String(), now()->addDay()))
    ->everyMinute()->name('scheduler-heartbeat');

// Cola de trabajos (exportaciones grandes, avisos por WhatsApp): sin servicio aparte en Windows/XAMPP.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

// Alertas: stock bajo, cargas pendientes, temperatura, vencimientos, mantenimiento, etc.
Schedule::command('galpon:check-alerts')->everyFiveMinutes()->withoutOverlapping(10);

// Backups automáticos (se pueden apagar en Configuración → Backups).
Schedule::command('galpon:backup --type=daily')->dailyAt('02:00')->withoutOverlapping(120)
    ->when(fn () => (bool) setting('backup.daily', true));
Schedule::command('galpon:backup --type=weekly')->weeklyOn(0, '03:00')->withoutOverlapping(120)
    ->when(fn () => (bool) setting('backup.weekly', true));

// Limpieza: enlaces de recuperación de contraseña vencidos y trabajos fallidos viejos.
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
