<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PasswordResetService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administración general → Usuarios: todos los datos de cada usuario para poder ayudarlo (se olvidó la
 * contraseña, el usuario o el email). Las contraseñas están cifradas y NO se pueden ver: se asigna una
 * temporal, que se muestra una sola vez y el usuario debe cambiar al ingresar. Cada consulta queda auditada.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users, private readonly AuditService $audit)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(UserStatus::options()))],
            'role_id' => ['nullable', 'integer'],
            'filter' => ['nullable', Rule::in(['temporary', 'never', 'inactive_30'])],
        ]);

        $users = User::query()
            ->with('role')
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.addcslashes((string) $term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)->orWhere('email', 'like', $like)->orWhere('dni', 'like', $like)
                    ->orWhere('phone', 'like', $like)->orWhere('cuit', 'like', $like)->orWhere('internal_code', 'like', $like));
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('role_id'), fn ($q, $role) => $q->where('role_id', (int) $role))
            ->when($request->query('filter') === 'temporary', fn ($q) => $q->where('must_change_password', true))
            ->when($request->query('filter') === 'never', fn ($q) => $q->whereNull('last_login_at'))
            ->when($request->query('filter') === 'inactive_30', fn ($q) => $q->where(fn ($w) => $w->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30))))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($this->perPage($request))->withQueryString();

        return view('superadmin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->audit->log('superadmin_view_user', $user, description: 'Consultó los datos de '.$user->username.' en la administración general');

        return view('superadmin.users.show', [
            'user' => $user->load('role', 'packer', 'warehouses'),
            'statuses' => UserStatus::options(),
            'activity' => AuditLog::query()->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhere(fn ($w) => $w->where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)))
                ->latest('created_at')->limit(25)->get(),
            'sessions' => config('session.driver') === 'database'
                ? \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->count()
                : null,
        ]);
    }

    /** Contraseña temporal: se muestra una sola vez y el usuario la cambia al ingresar. */
    public function resetPassword(Request $request, User $user, PasswordResetService $resets): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Tu propia contraseña se cambia desde «Mi perfil».');
        }
        $temporary = $resets->assignTemporary($user, $request->user());

        return redirect()->route('superadmin.users.show', $user)
            ->with('success', 'Contraseña temporal asignada. Se cerraron sus sesiones abiertas.')
            ->with('temporary_password', $temporary);
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'alpha_dash', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['username' => 'usuario', 'phone' => 'teléfono', 'reason' => 'motivo']);

        $this->users->updateAccessData($user, $data, $request->user(), $data['reason']);

        return redirect()->route('superadmin.users.show', $user)->with('success', 'Datos de acceso actualizados.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(UserStatus::options()))],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['status' => 'estado', 'reason' => 'motivo']);

        $this->users->setStatus($user, UserStatus::from($data['status']), $request->user(), $data['reason']);

        return redirect()->route('superadmin.users.show', $user)->with('success', 'Estado actualizado.');
    }

    public function terminateSessions(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Para cerrar tu propia sesión usá «Cerrar sesión».');
        }
        $this->users->terminateSessions($user, $request->user());

        return redirect()->route('superadmin.users.show', $user)->with('success', 'Se cerraron todas sus sesiones.');
    }
}
