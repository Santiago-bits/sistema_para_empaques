<?php

namespace App\Listeners;

use App\Events\BackupCompleted;
use App\Notifications\BackupFailed;
use App\Support\Recipients;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Si un backup falla se avisa a quienes gestionan backups. */
class NotifyBackupFailed
{
    public function handle(BackupCompleted $event): void
    {
        try {
            $backup = $event->backup;
            if ($backup->status !== 'failed') {
                return;
            }

            $users = Recipients::withPermission('backups.manage');
            if ($users->isNotEmpty()) {
                Notification::send($users, new BackupFailed($backup->getKey(), (string) $backup->type, $backup->error));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
