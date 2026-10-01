<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Registro de auditoría: responde QUÉ, QUIÉN, CUÁNDO, DÓNDE (IP) y POR QUÉ.
 * Los datos sensibles (contraseñas, tokens, credenciales) nunca se guardan.
 */
class AuditService
{
    private const SENSITIVE = ['password', 'token', 'secret', 'certificate', 'private_key', 'api_key', 'remember_token', 'license_key'];

    /** Motivo aplicado a los registros de esta request (p.ej. "corrección de peso"). */
    private ?string $pendingReason = null;

    private bool $muted = false;

    public function withReason(?string $reason): self
    {
        $this->pendingReason = $reason;

        return $this;
    }

    /** Ejecuta un callback sin auditoría automática (p.ej. seeders masivos). */
    public function muted(callable $callback): mixed
    {
        $previous = $this->muted;
        $this->muted = true;
        try {
            return $callback();
        } finally {
            $this->muted = $previous;
        }
    }

    public function log(
        string $action,
        ?Model $model = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        ?string $reason = null,
    ): ?AuditLog {
        if ($this->muted) {
            return null;
        }

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'description' => $description ? Str::limit($description, 250) : null,
            'old_values' => $old ? $this->sanitize($old) : null,
            'new_values' => $new ? $this->sanitize($new) : null,
            'reason' => $reason ?? $this->pendingReason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : 'console',
            'url' => $request ? Str::limit($request->fullUrl(), 250, '') : null,
            'created_at' => now(),
        ]);
    }

    public function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            foreach (self::SENSITIVE as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $values[$key] = '[oculto]';
                    continue 2;
                }
            }
            if ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
