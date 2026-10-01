<?php

namespace App\Events;

use App\Models\Backup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Finalizó un backup (exitoso o fallido). */
class BackupCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Backup $backup)
    {
    }
}
