<?php

namespace App\Console\Commands;

use App\Services\Central\CentralSyncService;
use Illuminate\Console\Command;
use Throwable;

/** Sincroniza este empaque con el Panel General del proveedor (uso, soporte y licencia). */
class CentralSyncCommand extends Command
{
    protected $signature = 'galpon:central-sync {--report : Enviar el reporte de uso aunque no haya pasado una hora}';

    protected $description = 'Envía el uso y los pedidos de soporte al Panel General y baja las respuestas';

    public function handle(CentralSyncService $sync): int
    {
        if (! $sync->enabled()) {
            $this->line('Panel General no configurado: nada que sincronizar.');

            return self::SUCCESS;
        }

        try {
            $result = $sync->sync((bool) $this->option('report'));
        } catch (Throwable $e) {
            $this->error('No se pudo conectar con el Panel General: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Listo. Reporte: %s · tickets subidos: %d · respuestas recibidas: %d · cambios de estado: %d',
            $result['report'] ? 'enviado' : 'no hacía falta', $result['tickets'], $result['messages'], $result['statuses']));

        return self::SUCCESS;
    }
}
