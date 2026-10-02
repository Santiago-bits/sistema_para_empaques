<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ReportReady;
use App\Services\ExportService;
use App\Services\Reports\ReportDatasets;
use App\Services\Reports\ReportFilters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera en segundo plano una exportación grande (> 20.000 filas) con los
 * mismos filtros que el usuario tenía en pantalla, la guarda en storage
 * privado (exports/{user_id}/{archivo}) y notifica al usuario (ReportReady).
 * La descarga (reports.downloads.show) verifica que el archivo sea del usuario.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly int $userId,
        public readonly string $report,
        public readonly string $variant,
        public readonly array $filters,
        public readonly string $format,
        public readonly string $file,
    ) {
    }

    public function handle(ReportDatasets $datasets, ExportService $exports): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $filters = ReportFilters::fromArray($this->filters);
        $dataset = $datasets->make($this->report, $this->variant, $filters, $user);
        $exports->store($dataset, $this->format, $filters, ExportService::pathFor($user->id, $this->file), $user);

        $user->notify(new ReportReady($dataset->title, $this->file, $this->format));
    }

    public function failed(Throwable $e): void
    {
        Log::error('No se pudo generar la exportación '.$this->file.': '.$e->getMessage());
        User::query()->find($this->userId)?->notify(new ReportReady(ReportDatasets::title($this->report), $this->file, $this->format, false));
    }
}
