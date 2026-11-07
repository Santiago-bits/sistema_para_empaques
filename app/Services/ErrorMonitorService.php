<?php

namespace App\Services;

use App\Models\SystemError;
use App\Notifications\ErrorDigestMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vigila los errores del sistema y avisa al desarrollador por correo:
 * - los errores técnicos registrados (system_errors, los que muestran un código ERR-…), y
 * - las líneas de error del log (storage/logs), que incluyen fallas de tareas programadas o de la cola.
 * Los agrupa por causa y manda un solo correo (como mucho uno cada `COOLDOWN_MINUTES`).
 *
 * Además limpia cada 7 días los errores y los logs viejos para que no ocupen lugar de más.
 */
class ErrorMonitorService
{
    /** Estado de la vigilancia: último error avisado y hasta dónde se leyó cada log. */
    public const STATE_FILE = 'framework/error-monitor.json';

    public const COOLDOWN_MINUTES = 15;

    /** Máximo que se lee de un log por corrida (si hay más, sigue en la próxima). */
    private const MAX_READ_BYTES = 2 * 1024 * 1024;

    private const MAX_GROUPS = 25;

    // ------------------------------------------------------------------ Aviso por correo

    /** Correo del desarrollador (el mismo de soporte). */
    public function recipient(): ?string
    {
        $email = trim((string) setting('support.email', ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** El servidor tiene configurado el envío de correo (en el .env: MAIL_MAILER=smtp…). */
    public function mailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }

    public function enabled(): bool
    {
        return (bool) setting('support.error_alerts', true);
    }

    /**
     * Busca errores nuevos y, si hay, los manda por correo agrupados.
     *
     * @return array{found: int, groups: int, sent: bool, reason: ?string}
     */
    public function check(bool $force = false): array
    {
        $state = $this->state();
        $groups = [];

        // Primera vez: se empieza a vigilar desde ahora (no se manda todo lo viejo de golpe).
        if (! isset($state['last_check_at'])) {
            $this->saveState([
                'last_error_id' => (int) SystemError::query()->max('id'),
                'offsets' => collect($this->logFiles())->mapWithKeys(fn ($p) => [basename($p) => (int) @filesize($p)])->all(),
                'pending' => [], 'last_check_at' => now()->toIso8601String(),
            ]);

            return ['found' => 0, 'groups' => 0, 'sent' => false, 'reason' => null];
        }

        // 1) Errores técnicos registrados por el sistema.
        $lastId = (int) ($state['last_error_id'] ?? 0);
        $errors = SystemError::query()->where('id', '>', $lastId)->orderBy('id')->limit(500)->get();
        $captured = [];
        foreach ($errors as $error) {
            $key = 'db:'.sha1($error->exception.'|'.$error->file.'|'.$error->line.'|'.Str::limit($error->message, 160, ''));
            $groups[$key] ??= ['title' => Str::limit($error->message, 300), 'where' => trim(($error->file ?? '').($error->line ? ':'.$error->line : '')),
                'kind' => $error->category, 'count' => 0, 'first' => (string) $error->created_at, 'last' => (string) $error->created_at, 'code' => $error->code, 'url' => $error->url];
            $groups[$key]['count']++;
            $groups[$key]['last'] = (string) $error->created_at;
            $captured[] = Str::limit((string) $error->message, 120, '');
        }
        $newLastId = $errors->isNotEmpty() ? (int) $errors->last()->id : $lastId;

        // 2) Líneas de error del log que no son los mismos errores de arriba.
        $offsets = $state['offsets'] ?? [];
        foreach ($this->logFiles() as $path) {
            $name = basename($path);
            [$entries, $offsets[$name]] = $this->readLog($path, (int) ($offsets[$name] ?? 0));
            foreach ($entries as $entry) {
                if (collect($captured)->contains(fn ($m) => $m !== '' && str_starts_with($entry['message'], $m))) {
                    continue;
                }
                $key = 'log:'.sha1(Str::limit(preg_replace('/\d+/', '#', $entry['message']), 160, ''));
                $groups[$key] ??= ['title' => Str::limit($entry['message'], 300), 'where' => $name, 'kind' => mb_strtolower($entry['level']),
                    'count' => 0, 'first' => $entry['at'], 'last' => $entry['at'], 'code' => null, 'url' => null];
                $groups[$key]['count']++;
                $groups[$key]['last'] = $entry['at'];
            }
        }
        $offsets = array_intersect_key($offsets, array_flip(array_map('basename', $this->logFiles())));

        // Lo pendiente de avisos anteriores (por ejemplo, si el correo falló o estaba en pausa) se suma.
        foreach ($state['pending'] ?? [] as $key => $group) {
            if (isset($groups[$key])) {
                $groups[$key]['count'] += $group['count'];
                $groups[$key]['first'] = $group['first'];
            } else {
                $groups[$key] = $group;
            }
        }
        $found = array_sum(array_column($groups, 'count'));

        $result = ['found' => $found, 'groups' => count($groups), 'sent' => false, 'reason' => null];
        $state = ['last_error_id' => $newLastId, 'offsets' => $offsets, 'pending' => [], 'last_sent_at' => $state['last_sent_at'] ?? null,
            'last_reset_at' => $state['last_reset_at'] ?? null, 'last_check_at' => now()->toIso8601String()];

        if ($groups === []) {
            $this->saveState($state);

            return $result;
        }

        $reason = match (true) {
            ! $this->enabled() => 'Los avisos de errores por correo están apagados.',
            $this->recipient() === null => 'No hay un correo de desarrollador válido configurado.',
            ! $this->mailConfigured() => 'El servidor no tiene configurado el envío de correo (MAIL_MAILER en el .env).',
            ! $force && ($state['last_sent_at'] ?? null) && now()->diffInMinutes($state['last_sent_at'], true) < self::COOLDOWN_MINUTES
                => 'Ya se mandó un aviso hace menos de '.self::COOLDOWN_MINUTES.' minutos: va en el próximo.',
            default => null,
        };

        if ($reason === null) {
            try {
                $list = collect($groups)->sortByDesc('count')->take(self::MAX_GROUPS)->values()->all();
                Notification::route('mail', $this->recipient())->notify(new ErrorDigestMail($list, $found, count($groups)));
                $state['last_sent_at'] = now()->toIso8601String();
                $result['sent'] = true;
            } catch (Throwable $e) {
                // Sin report(): sería un error nuevo por cada intento. Queda como advertencia y se reintenta.
                Log::warning('No se pudo mandar el aviso de errores por correo: '.$e->getMessage());
                $reason = 'No se pudo mandar el correo: '.Str::limit($e->getMessage(), 200);
            }
        }

        if (! $result['sent']) {
            // Se guarda para el próximo aviso (como mucho los más frecuentes, para no crecer sin fin).
            $state['pending'] = collect($groups)->sortByDesc('count')->take(self::MAX_GROUPS * 2)->all();
            $result['reason'] = $reason;
        }
        $this->saveState($state);
        Cache::put('error-monitor.last', $result + ['at' => now()->toIso8601String()], now()->addDays(8));

        return $result;
    }

    // ------------------------------------------------------------------ Limpieza semanal

    /**
     * Borra los errores técnicos y los logs de más de `$days` días, y vacía el log principal.
     * El historial de cambios (auditoría) NO se toca: es la trazabilidad del negocio.
     *
     * @return array{errors: int, files: int, bytes: int}
     */
    public function cleanup(int $days = 7): array
    {
        // Antes de borrar, se avisa lo que haya quedado sin avisar.
        try {
            $this->check(true);
        } catch (Throwable $e) {
            Log::warning('Aviso de errores antes de la limpieza: '.$e->getMessage());
        }

        $deleted = SystemError::query()->where('created_at', '<', now()->subDays($days))->delete();
        $files = 0;
        $bytes = 0;
        foreach ($this->logFiles() as $path) {
            $size = (int) @filesize($path);
            if (@filemtime($path) < now()->subDays($days)->getTimestamp()) {
                if (@unlink($path)) {
                    $files++;
                    $bytes += $size;
                }
            } elseif ($size > 0) {
                // El log en uso se vacía (no se borra: el servidor lo sigue usando).
                if (@file_put_contents($path, '') !== false) {
                    $files++;
                    $bytes += $size;
                }
            }
        }

        $state = $this->state();
        $state['offsets'] = [];
        $state['last_reset_at'] = now()->toIso8601String();
        $this->saveState($state);

        return ['errors' => $deleted, 'files' => $files, 'bytes' => $bytes];
    }

    /** Estado para mostrar en el panel del desarrollador. */
    public function status(): array
    {
        $state = $this->state();

        return [
            'recipient' => $this->recipient(),
            'enabled' => $this->enabled(),
            'mail_configured' => $this->mailConfigured(),
            'mailer' => (string) config('mail.default'),
            'last_sent_at' => $state['last_sent_at'] ?? null,
            'last_check_at' => $state['last_check_at'] ?? null,
            'last_reset_at' => $state['last_reset_at'] ?? null,
            'pending' => array_sum(array_column($state['pending'] ?? [], 'count')),
            'last' => Cache::get('error-monitor.last'),
            'log_bytes' => collect($this->logFiles())->sum(fn ($p) => (int) @filesize($p)),
        ];
    }

    // ------------------------------------------------------------------ Internos

    /** @return list<string> */
    private function logFiles(): array
    {
        return array_values(array_filter(glob(rtrim((string) config('galpon.logs_path', storage_path('logs')), '/').'/*.log') ?: [], 'is_file'));
    }

    /**
     * Lee las entradas de error de un log desde una posición.
     *
     * @return array{0: list<array{at: string, level: string, message: string}>, 1: int}
     */
    private function readLog(string $path, int $offset): array
    {
        $size = (int) @filesize($path);
        if ($size < $offset) {
            $offset = 0; // El archivo se vació o se reemplazó.
        }
        if ($size === $offset) {
            return [[], $offset];
        }

        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return [[], $offset];
        }
        fseek($handle, $offset);
        $chunk = (string) fread($handle, self::MAX_READ_BYTES);
        fclose($handle);

        // Si se cortó a la mitad de una línea, esa línea se lee completa en la próxima corrida.
        $end = strrpos($chunk, "\n");
        if ($end === false) {
            return [[], $offset];
        }
        $chunk = substr($chunk, 0, $end + 1);

        $entries = [];
        foreach (explode("\n", $chunk) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:]+)[^\]]*\]\s+[\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):\s?(.*)$/', $line, $m)) {
                // El mensaje sin el contexto JSON del final (traza, usuario…).
                $message = trim(preg_replace('/\s+\{.*\}\s*$/', '', $m[3]) ?? $m[3]);
                $entries[] = ['at' => $m[1], 'level' => $m[2], 'message' => Str::limit($message, 500)];
            }
        }

        return [$entries, $offset + strlen($chunk)];
    }

    private function state(): array
    {
        $path = storage_path(self::STATE_FILE);
        $data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }

    private function saveState(array $state): void
    {
        File::ensureDirectoryExists(dirname(storage_path(self::STATE_FILE)));
        @file_put_contents(storage_path(self::STATE_FILE), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
