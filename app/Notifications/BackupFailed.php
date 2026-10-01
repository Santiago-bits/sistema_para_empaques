<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsToDatabaseAndWhatsApp;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Un backup falló: hay que revisarlo cuanto antes. */
class BackupFailed extends Notification
{
    use SendsToDatabaseAndWhatsApp;

    public function __construct(public readonly ?int $backupId, public readonly string $type, public readonly ?string $error)
    {
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            'backup_failed',
            'Falló el backup '.($this->type === 'manual' ? 'manual' : 'automático'),
            Str::limit((string) $this->error, 300) ?: 'Revisá la pantalla de backups.',
            self::safeRoute('backups.index'),
            'danger',
            ['backup_id' => $this->backupId],
        );
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return '⚠ '.setting('company.name', 'Galpón').': falló el backup '.$this->type.'. Revisá el sistema.';
    }
}
