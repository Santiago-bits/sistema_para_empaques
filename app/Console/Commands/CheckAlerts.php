<?php

namespace App\Console\Commands;

use App\Services\AlertChecker;
use Illuminate\Console\Command;

class CheckAlerts extends Command
{
    protected $signature = 'galpon:check-alerts';

    protected $description = 'Evalúa las condiciones de alerta habilitadas y resuelve las que ya no aplican';

    public function handle(AlertChecker $checker): int
    {
        $summary = $checker->run();

        $labels = trans('alerts.types');
        $statusLabels = ['ok' => 'evaluada', 'disabled' => 'deshabilitada', 'module_disabled' => 'módulo apagado', 'error' => 'ERROR'];

        $this->table(['Alerta', 'Estado', 'Activas', 'Resueltas'], collect($summary)->map(fn ($row, $type) => [
            is_array($labels) ? ($labels[$type] ?? $type) : $type,
            $statusLabels[$row['status']] ?? $row['status'],
            $row['active'] ?? '—',
            $row['resolved'] ?? '—',
        ])->values()->all());

        return collect($summary)->contains(fn ($row) => $row['status'] === 'error') ? self::FAILURE : self::SUCCESS;
    }
}
