<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSuperAdminConfirmed;
use App\Models\AuditLog;
use App\Models\ClientTicket;
use App\Models\License;
use App\Models\LicensePayment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Administración general del sistema (/administradorgeneral): un solo lugar para el dueño del sistema con
 * los usuarios, los clientes (si pagaron y si usan el sistema) y los pedidos de soporte.
 */
class HubController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        if (EnsureSuperAdminConfirmed::confirmed($request)) {
            return redirect()->route('superadmin.index');
        }

        return view('superadmin.confirm', ['minutes' => EnsureSuperAdminConfirmed::CONFIRM_MINUTES]);
    }

    public function confirmStore(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'max:255']], [], ['password' => 'contraseña']);

        if (! Hash::check((string) $request->input('password'), (string) $request->user()->getAuthPassword())) {
            $this->audit->log('superadmin_denied', $request->user(), description: 'Contraseña incorrecta al entrar a la administración general');

            return back()->withErrors(['password' => 'La contraseña no es correcta.']);
        }

        $request->session()->put(EnsureSuperAdminConfirmed::SESSION_KEY, now()->getTimestamp());
        $this->audit->log('superadmin_access', $request->user(), description: 'Entró a la administración general');

        return redirect()->intended(route('superadmin.index'));
    }

    /** Cierra el acceso a la administración general sin cerrar la sesión del sistema. */
    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget(EnsureSuperAdminConfirmed::SESSION_KEY);

        return redirect()->route('home')->with('success', 'Saliste de la administración general.');
    }

    public function index(): View
    {
        $central = (bool) config('galpon.central.mode');
        $clients = $central ? License::query()->with('latestReport')->get() : collect();
        $byPayment = $clients->groupBy(fn (License $l) => $l->paymentStatus());

        return view('superadmin.index', [
            'central' => $central,
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', UserStatus::Active->value)->count(),
                'temporary' => User::query()->where('must_change_password', true)->count(),
                'never' => User::query()->whereNull('last_login_at')->count(),
                'failed_24h' => AuditLog::query()->where('action', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
            'clients' => [
                'total' => $clients->count(),
                'online' => $clients->filter->isOnline()->count(),
                'ok' => $byPayment->get('ok', collect())->count(),
                'due_soon' => $byPayment->get('due_soon', collect())->count(),
                'overdue' => $byPayment->get('overdue', collect())->count() + $byPayment->get('never', collect())->count(),
                'month_income' => $central ? (float) LicensePayment::query()->valid()->whereBetween('paid_at', [today()->startOfMonth(), today()->endOfMonth()])->sum('amount') : 0,
            ],
            'attention' => $clients->filter(fn (License $l) => in_array($l->paymentStatus(), ['overdue', 'never', 'due_soon'], true))
                ->sortBy(fn (License $l) => $l->paid_until?->timestamp ?? 0)->take(8),
            'offline' => $clients->reject->isOnline()->sortBy(fn (License $l) => $l->last_seen_at?->timestamp ?? 0)->take(8),
            'tickets' => $central ? ClientTicket::query()->with('license')->whereNotIn('status', ['resolved', 'closed'])
                ->orderByDesc('last_message_at')->limit(8)->get() : collect(),
            'openTickets' => $central ? ClientTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count() : 0,
            'failedLogins' => AuditLog::query()->where('action', 'login_failed')->latest('created_at')->limit(6)->get(),
        ]);
    }

    /** Activa la gestión de clientes (Panel General) en este servidor: clientes, pagos, uso y soporte. */
    public function centralPanel(Request $request, SettingsService $settings): RedirectResponse
    {
        $enable = $request->boolean('enable');
        $settings->set('system.central_panel', $enable);
        $this->audit->log('settings', null, null, ['system.central_panel' => $enable],
            $enable ? 'Activó la gestión de clientes (Panel General)' : 'Desactivó la gestión de clientes (Panel General)');

        return redirect()->route('superadmin.index')->with('success', $enable
            ? 'Gestión de clientes activada: ya podés cargar empaques clientes, sus pagos y recibir sus pedidos de soporte.'
            : 'Gestión de clientes desactivada. Los datos cargados se conservan.');
    }
}
