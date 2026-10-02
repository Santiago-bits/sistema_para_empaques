<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Crate;
use App\Models\Incident;
use App\Models\Load;
use App\Models\Pallet;
use App\Models\StateHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Incidentes operativos (cajón faltante, error de peso, transporte, etc.) con número propio,
 * responsable, historial de estados y vínculo opcional a un cajón, pallet o carga.
 */
class IncidentService
{
    /** Transiciones válidas de estado. */
    public const TRANSITIONS = [
        'open' => ['in_progress', 'resolved', 'closed'],
        'in_progress' => ['open', 'resolved', 'closed'],
        'resolved' => ['in_progress', 'closed'],
        'closed' => ['in_progress'],
    ];

    /** Tipos de registro que se pueden vincular, por el código que se escribe/escanea. */
    public const RELATED = ['crate' => 'Cajón', 'pallet' => 'Pallet', 'load' => 'Carga'];

    public function __construct(private readonly SequenceService $sequences, private readonly AlertService $alerts)
    {
    }

    public function create(array $data, User $by): Incident
    {
        $related = $this->resolveRelated($data['related_type'] ?? null, $data['related_code'] ?? null);

        $incident = DB::transaction(function () use ($data, $by, $related) {
            $incident = Incident::query()->create([
                'number' => $this->sequences->next('incident'),
                'type' => $data['type'],
                'occurred_at' => $data['occurred_at'],
                'area' => $data['area'] ?? null,
                'description' => $data['description'],
                'priority' => $data['priority'],
                'status' => 'open',
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'reported_by' => $by->id,
                'responsible_id' => $data['responsible_id'] ?? null,
            ]);
            $this->history($incident, null, 'open', $by, 'Incidente registrado');

            return $incident;
        });

        if (in_array($incident->priority, ['high', 'critical'], true)) {
            $this->alerts->raise('incident', 'Incidente '.$incident->number.': '.(Incident::TYPES[$incident->type] ?? $incident->type),
                \Illuminate\Support\Str::limit($incident->description, 200), $incident, $incident->priority === 'critical' ? 'critical' : 'warning');
        }

        return $incident;
    }

    public function update(Incident $incident, array $data): Incident
    {
        $related = $this->resolveRelated($data['related_type'] ?? null, $data['related_code'] ?? null);
        $incident->update([
            'type' => $data['type'], 'occurred_at' => $data['occurred_at'], 'area' => $data['area'] ?? null,
            'description' => $data['description'], 'priority' => $data['priority'], 'responsible_id' => $data['responsible_id'] ?? null,
            'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(),
        ]);

        return $incident;
    }

    /** Cambio de estado atómico: sólo se aplica si nadie lo cambió antes (UPDATE condicional). */
    public function changeStatus(Incident $incident, string $to, User $by, ?string $resolution = null): Incident
    {
        $from = $incident->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new BusinessException('No se puede pasar de «'.Incident::STATUSES[$from].'» a «'.(Incident::STATUSES[$to] ?? $to).'».');
        }
        if (in_array($to, ['resolved', 'closed'], true) && trim((string) ($resolution ?? $incident->resolution)) === '') {
            throw new BusinessException('Contá cómo se resolvió antes de marcarlo como resuelto o cerrado.');
        }

        DB::transaction(function () use ($incident, $from, $to, $by, $resolution) {
            $changes = ['status' => $to, 'updated_at' => now()];
            if ($resolution !== null && trim($resolution) !== '') {
                $changes['resolution'] = $resolution;
            }
            $changes['resolved_at'] = in_array($to, ['resolved', 'closed'], true) ? ($incident->resolved_at ?? now()) : null;

            $affected = Incident::query()->whereKey($incident->id)->where('status', $from)->update($changes);
            if ($affected !== 1) {
                throw new BusinessException('Otro usuario cambió el estado del incidente. Actualizá la página.');
            }
            $this->history($incident, $from, $to, $by, $resolution);
            app(AuditService::class)->log('status_change', $incident, ['status' => $from], ['status' => $to], 'Incidente '.$incident->number.': '.Incident::STATUSES[$to], $resolution);
        });

        if (in_array($to, ['resolved', 'closed'], true)) {
            $this->alerts->resolveByFingerprint('incident:'.$incident->getMorphClass().':'.$incident->id, $by);
        }

        return $incident->refresh();
    }

    private function resolveRelated(?string $type, ?string $code): ?Model
    {
        if (! $type || trim((string) $code) === '') {
            return null;
        }
        $code = mb_strtoupper(trim($code));

        $model = match ($type) {
            'crate' => Crate::query()->where('code', $code)->first(),
            'pallet' => Pallet::query()->where('code', $code)->first(),
            'load' => Load::query()->where('number', $code)->first(),
            default => null,
        };

        if (! $model) {
            throw new BusinessException('No existe '.mb_strtolower(self::RELATED[$type] ?? 'el registro').' con código '.$code.'.');
        }

        return $model;
    }

    private function history(Incident $incident, ?string $from, string $to, User $by, ?string $notes): void
    {
        StateHistory::query()->create([
            'stateful_type' => $incident->getMorphClass(), 'stateful_id' => $incident->id,
            'from_state' => $from, 'to_state' => $to, 'user_id' => $by->id,
            'notes' => $notes ? \Illuminate\Support\Str::limit($notes, 250) : null, 'created_at' => now(),
        ]);
    }
}
