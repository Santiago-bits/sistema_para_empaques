<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertRaised;
use App\Support\Recipients;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alertas del sistema. Cada alerta tiene una huella (fingerprint) única: mientras la
 * condición siga vigente no se duplica; si se había resuelto y la condición vuelve,
 * se reabre. Las alertas nuevas warning/critical se notifican a quienes tienen alerts.view.
 */
class AlertService
{
    public const SEVERITIES = ['info' => 'Información', 'warning' => 'Advertencia', 'critical' => 'Crítica'];

    private const RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    public function raise(
        string $type,
        string $title,
        ?string $message = null,
        ?Model $alertable = null,
        string $severity = 'warning',
        ?string $fingerprint = null,
    ): Alert {
        $severity = array_key_exists($severity, self::SEVERITIES) ? $severity : 'warning';
        $fingerprint = Str::limit($fingerprint ?? $this->defaultFingerprint($type, $title, $alertable), 120, '');

        $attributes = [
            'type' => Str::limit($type, 40, ''),
            'severity' => $severity,
            'title' => Str::limit($title, 250),
            'message' => $message,
            'alertable_type' => $alertable?->getMorphClass(),
            'alertable_id' => $alertable?->getKey(),
        ];

        [$alert, $notify] = $this->upsert($fingerprint, $attributes);

        if ($notify) {
            $this->notify($alert);
        }

        return $alert;
    }

    public function resolve(Alert $alert, ?User $by = null): Alert
    {
        if ($alert->resolved_at === null) {
            // UPDATE condicional: si dos procesos resuelven a la vez, sólo uno escribe.
            Alert::query()->whereKey($alert->getKey())->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $by?->getKey(), 'updated_at' => now()]);
            $alert->refresh();
        }

        return $alert;
    }

    public function resolveByFingerprint(string $fingerprint, ?User $by = null): bool
    {
        return Alert::query()->where('fingerprint', $fingerprint)->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $by?->getKey(), 'updated_at' => now()]) > 0;
    }

    /**
     * Resuelve automáticamente las alertas abiertas de un tipo cuya condición ya no existe.
     *
     * @param  list<string>  $activeFingerprints
     */
    public function resolveStale(string $type, array $activeFingerprints): int
    {
        $count = 0;
        Alert::query()->open()->where('type', $type)
            ->when($activeFingerprints !== [], fn ($q) => $q->whereNotIn('fingerprint', $activeFingerprints))
            ->select(['id', 'fingerprint'])
            ->chunkById(200, function ($alerts) use (&$count) {
                $count += Alert::query()->whereIn('id', $alerts->pluck('id'))->whereNull('resolved_at')
                    ->update(['resolved_at' => now(), 'resolved_by' => null, 'updated_at' => now()]);
            });

        return $count;
    }

    /** @return array{0: Alert, 1: bool} alerta y si corresponde notificar */
    private function upsert(string $fingerprint, array $attributes): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($fingerprint, $attributes) {
                    $alert = Alert::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();

                    if (! $alert) {
                        $alert = Alert::query()->create($attributes + ['fingerprint' => $fingerprint]);

                        return [$alert, true];
                    }

                    $reopened = $alert->resolved_at !== null;
                    $escalated = (self::RANK[$attributes['severity']] ?? 1) > (self::RANK[$alert->severity] ?? 1);

                    $alert->fill($attributes);
                    if ($reopened) {
                        $alert->resolved_at = null;
                        $alert->resolved_by = null;
                    }
                    if ($alert->isDirty()) {
                        $alert->save();
                    }

                    return [$alert, $reopened || $escalated];
                });
            } catch (UniqueConstraintViolationException) {
                // Otro proceso creó la misma alerta en paralelo: se reintenta como actualización.
                continue;
            }
        }

        return [Alert::query()->where('fingerprint', $fingerprint)->firstOrFail(), false];
    }

    private function notify(Alert $alert): void
    {
        if (! in_array($alert->severity, ['warning', 'critical'], true)) {
            return;
        }

        try {
            $users = Recipients::withPermission('alerts.view');
            if ($users->isNotEmpty()) {
                Notification::send($users, new AlertRaised($alert));
            }
        } catch (Throwable $e) {
            // Una falla al notificar nunca debe impedir registrar la alerta.
            report($e);
        }
    }

    private function defaultFingerprint(string $type, string $title, ?Model $alertable): string
    {
        return $alertable
            ? $type.':'.$alertable->getMorphClass().':'.$alertable->getKey()
            : $type.':'.md5($title);
    }
}
