<?php

namespace App\Console\Commands;

use App\Services\ErrorMonitorService;
use Illuminate\Console\Command;

/** Revisa errores nuevos (registrados y en el log) y avisa al desarrollador por correo. */
class ErrorWatchCommand extends Command
{
    protected $signature = 'galpon:errors-watch';

    protected $description = 'Avisa por correo al desarrollador los errores nuevos del sistema';

    public function handle(ErrorMonitorService $monitor): int
    {
        $result = $monitor->check();
        $this->info($result['found'] === 0 ? 'Sin errores nuevos.'
            : $result['found'].' errores nuevos ('.$result['groups'].' tipos). '.($result['sent'] ? 'Aviso enviado.' : 'Sin enviar: '.$result['reason']));

        return self::SUCCESS;
    }
}
