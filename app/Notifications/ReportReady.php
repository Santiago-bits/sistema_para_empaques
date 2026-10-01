<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Aviso (en la campanita) de que una exportación en segundo plano está lista para descargar. */
class ReportReady extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $file,
        public readonly string $format,
        public readonly bool $success = true,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_ready',
            'title' => $this->success ? 'Exportación lista' : 'Falló la exportación',
            'message' => $this->success
                ? 'El reporte «'.$this->title.'» ('.strtoupper($this->format).') está listo para descargar.'
                : 'No se pudo generar el reporte «'.$this->title.'». Intentá nuevamente o acotá los filtros.',
            'file' => $this->success ? $this->file : null,
            'format' => $this->format,
            'url' => $this->success ? route('reports.downloads.show', ['file' => $this->file]) : null,
            'success' => $this->success,
        ];
    }
}
