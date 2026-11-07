<?php

namespace App\Console\Commands;

use App\Services\ErrorMonitorService;
use Illuminate\Console\Command;

/** Limpieza semanal: errores técnicos y logs de más de 7 días (la auditoría no se toca). */
class CleanupLogsCommand extends Command
{
    protected $signature = 'galpon:cleanup-logs {--days=7}';

    protected $description = 'Borra errores técnicos y logs viejos (por defecto, de más de 7 días)';

    public function handle(ErrorMonitorService $monitor): int
    {
        $r = $monitor->cleanup(max(1, (int) $this->option('days')));
        $this->info("Errores borrados: {$r['errors']} · archivos de log limpiados: {$r['files']} (".round($r['bytes'] / 1024).' KB)');

        return self::SUCCESS;
    }
}
