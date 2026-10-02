<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessException;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'galpon:backup {--type=manual : manual | daily | weekly}';

    protected $description = 'Genera un backup de la base de datos (comprimido, con checksum y verificación)';

    public function handle(BackupService $backups): int
    {
        $type = (string) $this->option('type');
        if (! in_array($type, ['manual', 'daily', 'weekly'], true)) {
            $this->error('Tipo inválido. Usá manual, daily o weekly.');

            return self::INVALID;
        }

        $this->info('Generando backup '.$type.'…');

        try {
            $backup = $backups->run($type);
        } catch (BusinessException $e) {
            $this->warn($e->getMessage());

            return self::FAILURE;
        }

        if ($backup->status !== 'success') {
            $this->error('El backup falló: '.$backup->error);

            return self::FAILURE;
        }

        $this->info('Backup generado: '.$backup->filename.' ('.$backups->humanSize($backup->size).')');
        $this->line('SHA-256: '.$backup->checksum);
        $this->line($backup->verified_at ? 'Verificación: correcta' : 'Verificación: FALLIDA — '.$backup->error);

        return $backup->verified_at ? self::SUCCESS : self::FAILURE;
    }
}
