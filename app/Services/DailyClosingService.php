<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\DailyClosing;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\ReportFilters;
use App\Support\CurrentWarehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cierre diario: guarda un snapshot histórico del día. Una vez cerrado, el resumen no
 * cambia aunque se modifiquen datos después (esas modificaciones quedan en auditoría).
 */
class DailyClosingService
{
    public function __construct(private readonly ReportService $reports, private readonly AuditService $audit)
    {
    }

    /** Resumen del día calculado ahora (vista previa o snapshot). */
    public function snapshot(Carbon $date): array
    {
        $f = ReportFilters::forDay($date);
        $ind = $this->reports->indicators($f);

        return [
            'date' => $date->toDateString(),
            'crates_in' => $ind['crates_in'],
            'crates_processed' => $ind['crates_processed'],
            'kg_processed' => $ind['kg_processed'],
            'kg_in' => $ind['kg_in'],
            'pallets_in' => $ind['pallets_in'],
            'rejects' => $ind['rejects'],
            'kg_rejected' => $ind['kg_rejected'],
            'waste_pct' => $ind['waste_pct'],
            'loads_dispatched' => $ind['loads_dispatched'],
            'kg_dispatched' => $ind['kg_dispatched'],
            'trucks' => $ind['trucks_dispatched'],
            'packers' => $ind['packers'],
            'invoices' => Invoice::query()->where('status', 'authorized')->whereDate('issued_on', $date)->count(),
            'incidents' => Incident::query()->whereDate('occurred_at', $date)->count(),
            'by_variety' => array_map(fn ($r) => ['label' => $r['label'], 'crates' => $r['crates'], 'kg' => $r['kg']], $this->reports->productionBy('variety', $f)),
            'by_packer' => array_map(fn ($r) => ['label' => $r['label'], 'crates' => $r['crates'], 'kg' => $r['kg']], $this->reports->productionBy('packer', $f)),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function close(Carbon $date, User $by, ?string $notes = null): DailyClosing
    {
        if ($date->isFuture()) {
            throw new BusinessException('No se puede cerrar un día futuro.');
        }

        return DB::transaction(function () use ($date, $by, $notes) {
            $existing = DailyClosing::query()->where('warehouse_id', CurrentWarehouse::id())->whereDate('date', $date)->lockForUpdate()->first();
            if ($existing && $existing->reopened_at === null) {
                throw new BusinessException('El día '.$date->format('d/m/Y').' ya está cerrado.');
            }

            $data = ['snapshot' => $this->snapshot($date), 'closed_by' => $by->id, 'closed_at' => now(),
                'reopened_at' => null, 'reopened_by' => null, 'reopen_reason' => null, 'notes' => $notes];

            try {
                $closing = $existing
                    ? tap($existing)->update($data)
                    : DailyClosing::query()->create($data + ['date' => $date->toDateString()]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessException('Otro usuario cerró este día al mismo tiempo.');
            }

            $this->audit->log('daily_closing', $closing, null, ['date' => $date->toDateString()], 'Cierre diario del '.$date->format('d/m/Y'), $notes);

            return $closing;
        }, 3); // reintenta ante un deadlock de InnoDB (dos cierres del mismo día a la vez)
    }

    public function reopen(DailyClosing $closing, string $reason, User $by): DailyClosing
    {
        return DB::transaction(function () use ($closing, $reason, $by) {
            $locked = DailyClosing::query()->whereKey($closing->id)->lockForUpdate()->firstOrFail();
            if ($locked->reopened_at !== null) {
                throw new BusinessException('El cierre ya estaba reabierto.');
            }
            $locked->update(['reopened_at' => now(), 'reopened_by' => $by->id, 'reopen_reason' => $reason]);
            $this->audit->log('reopen', $locked, null, ['date' => $locked->date->toDateString()], 'Reabrió el cierre del '.$locked->date->format('d/m/Y'), $reason);

            return $locked;
        });
    }

    public function isClosed(Carbon $date): bool
    {
        return DailyClosing::query()->where('warehouse_id', CurrentWarehouse::id())->whereDate('date', $date)->whereNull('reopened_at')->exists();
    }
}
