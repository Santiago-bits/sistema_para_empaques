<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sesiones activas (driver de sesión "database"): permite ver quién está
 * conectado y cerrar sesiones remotamente.
 */
class SessionService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function active(int $minutes = 120): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $since = now()->subMinutes($minutes)->getTimestamp();

        $rows = DB::table(config('session.table', 'sessions'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $users = User::query()->visibleTo()->with('role')->whereIn('id', $rows->pluck('user_id')->unique())->get()->keyBy('id');

        return $rows->map(fn ($row) => (object) [
            'id' => $row->id,
            'user' => $users[$row->user_id] ?? null,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'last_activity' => \Illuminate\Support\Carbon::createFromTimestamp($row->last_activity),
        ])->filter(fn ($s) => $s->user !== null)->values();
    }

    public function terminate(string $sessionId): void
    {
        $row = DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->first();
        if (! $row) {
            return;
        }
        $user = $row->user_id ? User::query()->find($row->user_id) : null;
        // La sesión del Super Administrador no existe para los demás.
        abort_if($user?->isHiddenFromViewer(), 404);
        DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete();
        $this->audit->log('session_terminated', $user, description: 'Sesión cerrada remotamente (IP '.$row->ip_address.')');
    }

    public function terminateAllFor(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => null])->saveQuietly();
    }
}
