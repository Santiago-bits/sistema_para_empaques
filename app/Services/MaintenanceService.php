<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mantenimiento de maquinaria: registro de mantenimientos y próximos vencimientos.
 */
class MaintenanceService
{
    /** Días de anticipación para resaltar un mantenimiento próximo. */
    public const UPCOMING_DAYS = 7;

    /**
     * Registra un mantenimiento. Opcionalmente actualiza el próximo mantenimiento y el estado de la máquina.
     *
     * $data: type, date, technician, cost, parts_used, notes, next_maintenance_on, machine_status.
     */
    public function register(Machine $machine, array $data): Maintenance
    {
        return DB::transaction(function () use ($machine, $data) {
            $maintenance = Maintenance::query()->create([
                'machine_id' => $machine->id,
                'type' => $data['type'],
                'date' => $data['date'],
                'technician' => $data['technician'] ?? null,
                'cost' => $data['cost'] ?? null,
                'parts_used' => $data['parts_used'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $changes = [];
            if (! empty($data['next_maintenance_on'])) {
                $changes['next_maintenance_on'] = $data['next_maintenance_on'];
            }
            if (! empty($data['machine_status']) && array_key_exists($data['machine_status'], Machine::STATUSES)) {
                $changes['status'] = $data['machine_status'];
            }
            if ($changes) {
                // Con el modelo (no query) para que la auditoría registre valor anterior/nuevo.
                $machine->update($changes);
            }

            return $maintenance;
        });
    }

    /** Situación del próximo mantenimiento: overdue | upcoming | ok | none. */
    public static function dueStatus(Machine $machine, int $days = self::UPCOMING_DAYS): string
    {
        if (! $machine->next_maintenance_on) {
            return 'none';
        }
        if ($machine->next_maintenance_on->lt(today())) {
            return 'overdue';
        }

        return $machine->next_maintenance_on->lte(today()->addDays($days)) ? 'upcoming' : 'ok';
    }

    /** Máquinas con mantenimiento vencido o dentro de los próximos $days días. */
    public function upcoming(int $days = self::UPCOMING_DAYS): Collection
    {
        return Machine::query()->whereNotNull('next_maintenance_on')
            ->whereDate('next_maintenance_on', '<=', today()->addDays($days))
            ->orderBy('next_maintenance_on')->get();
    }

    /** @return array{machines: int, operational: int, in_maintenance: int, out_of_service: int, overdue: int, upcoming: int} */
    public function summary(int $days = self::UPCOMING_DAYS): array
    {
        $byStatus = Machine::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'machines' => (int) $byStatus->sum(),
            'operational' => (int) ($byStatus['operational'] ?? 0),
            'in_maintenance' => (int) ($byStatus['maintenance'] ?? 0),
            'out_of_service' => (int) ($byStatus['out_of_service'] ?? 0),
            'overdue' => Machine::query()->whereDate('next_maintenance_on', '<', today())->count(),
            'upcoming' => Machine::query()->whereDate('next_maintenance_on', '>=', today())
                ->whereDate('next_maintenance_on', '<=', today()->addDays($days))->count(),
        ];
    }
}
