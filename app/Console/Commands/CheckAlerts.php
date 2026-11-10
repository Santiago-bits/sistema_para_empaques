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
        // Multigalpón: cada galpón con sus propias alertas, su configuración y su gente.
        $summary = [];
        $merge = function (array $part) use (&$summary) {
            foreach ($part as $type => $row) {
                $prev = $summary[$type] ?? null;
                $summary[$type] = $prev === null ? $row : [
                    'status' => $prev['status'] === 'error' || $row['status'] === 'error' ? 'error' : ($prev['status'] === 'ok' ? 'ok' : $row['status']),
                    'active' => ($prev['active'] ?? 0) + ($row['active'] ?? 0),
                    'resolved' => ($prev['resolved'] ?? 0) + ($row['resolved'] ?? 0),
                ];
            }
        };
        if (\App\Support\Tenancy::ready()) {
            \App\Support\CurrentWarehouse::forEach(fn () => $merge(app(AlertChecker::class)->run()));
        } else {
            $merge($checker->run());
        }

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
