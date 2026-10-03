<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actividad del personal en el galpón: quién entra, cuánto usa el sistema y en qué partes.
 * Todo sale de la auditoría, los registros de producción y las sesiones (consultas agrupadas).
 */
class UserActivityService
{
    /** Partes del sistema según el tipo de registro auditado. */
    public const AREAS = [
        'crate' => 'Cajones', 'production_record' => 'Producción / romaneo', 'pallet' => 'Pallets', 'lot' => 'Lotes',
        'load' => 'Cargas', 'remito' => 'Remitos', 'invoice' => 'Facturación', 'quality_control' => 'Calidad', 'reject' => 'Rechazos',
        'account_movement' => 'Cuentas corrientes', 'cash_movement' => 'Caja', 'cash_session' => 'Caja', 'check' => 'Cheques',
        'exchange_rate' => 'Cotización', 'supply' => 'Insumos', 'inventory_movement' => 'Insumos', 'incident' => 'Incidentes',
        'user' => 'Usuarios', 'setting' => 'Configuración', 'cost' => 'Costos', 'location' => 'Ubicaciones',
        'temperature_record' => 'Cámaras', 'cold_room' => 'Cámaras', 'support_ticket' => 'Soporte',
    ];

    /** @return array{users: Collection, totals: array, daily: Collection, areas: array} */
    public function summary(Carbon $from): array
    {
        $since = $from->copy()->startOfDay();

        $audit = DB::table('audit_logs')->where('created_at', '>=', $since)->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw("user_id, SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as logins")
            ->selectRaw("SUM(CASE WHEN action NOT IN ('login', 'login_failed', 'logout') THEN 1 ELSE 0 END) as actions")
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as days, MAX(created_at) as last_action')
            ->get()->keyBy('user_id');

        $crates = DB::table('production_records')->whereNull('voided_at')->where('recorded_at', '>=', $since)
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as n')->pluck('n', 'user_id');

        $online = DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes(10)->getTimestamp())
            ->distinct()->pluck('user_id')->flip();

        $users = User::query()->visibleTo()->with('role:id,name,slug')->where('status', 'active')->orderBy('last_name')->get()
            ->map(function (User $user) use ($audit, $crates, $online) {
                $row = $audit->get($user->id);

                return (object) [
                    'user' => $user,
                    'logins' => (int) ($row->logins ?? 0),
                    'actions' => (int) ($row->actions ?? 0),
                    'days' => (int) ($row->days ?? 0),
                    'crates' => (int) ($crates[$user->id] ?? 0),
                    'last_action' => isset($row->last_action) ? Carbon::parse($row->last_action) : null,
                    'online' => $online->has($user->id),
                ];
            })
            ->sortByDesc(fn ($r) => [$r->actions + $r->crates, $r->logins])->values();

        $daily = DB::table('audit_logs')->where('created_at', '>=', $since)->whereNotNull('user_id')
            ->groupByRaw('DATE(created_at)')->orderByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT user_id) as users, COUNT(*) as actions')->get();

        $areas = [];
        DB::table('audit_logs')->where('created_at', '>=', $since)->whereNotNull('auditable_type')
            ->groupBy('auditable_type')->selectRaw('auditable_type, COUNT(*) as n')->get()
            ->each(function ($r) use (&$areas) {
                $label = self::AREAS[$r->auditable_type] ?? null;
                if ($label) {
                    $areas[$label] = ($areas[$label] ?? 0) + (int) $r->n;
                }
            });
        arsort($areas);

        return [
            'users' => $users,
            'totals' => [
                'active' => $users->filter(fn ($r) => $r->logins + $r->actions + $r->crates > 0)->count(),
                'total' => $users->count(),
                'logins' => $users->sum('logins'),
                'actions' => $users->sum('actions'),
                'crates' => $users->sum('crates'),
                'online' => $users->where('online', true)->count(),
            ],
            'daily' => $daily,
            'areas' => array_slice($areas, 0, 8, true),
        ];
    }
}
