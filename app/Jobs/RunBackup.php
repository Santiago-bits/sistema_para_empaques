<?php

namespace App\Jobs;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/** Genera un backup en segundo plano (botón "Backup manual"). */
class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $type = 'manual', public readonly ?int $userId = null)
    {
    }

    public function handle(BackupService $backups): void
    {
        try {
            $backups->run($this->type, $this->userId ? User::query()->find($this->userId) : null);
        } catch (BusinessException $e) {
            // P.ej. otro backup en curso: no es una falla técnica.
            Log::info('Backup '.$this->type.' omitido: '.$e->getMessage());
        }
    }
}
