<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Owner;
use App\Models\Packer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PasswordResetService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('username', 'like', $term)->orWhere('dni', 'like', $term)->orWhere('internal_code', 'like', $term));
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', $this->formData(new User(['status' => UserStatus::Active])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return redirect()->route('admin.users.show', $user)->with('success', 'Usuario creado.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load('role', 'packer', 'warehouses'),
            'activity' => AuditLog::query()->where('user_id', $user->id)->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.show', $user)->with('success', 'Usuario actualizado.');
    }

    /** Asigna una contraseña temporal (el usuario debe cambiarla al ingresar). Se muestra una sola vez. */
    public function resetPassword(Request $request, User $user, PasswordResetService $resets): RedirectResponse
    {
        $this->authorize('update', $user);
        $temporary = $resets->assignTemporary($user, $request->user());

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Contraseña temporal asignada. Se cerraron sus sesiones abiertas.')
            ->with('temporary_password', $temporary);
    }

    public function permissions(User $user): View
    {
        $this->authorize('managePermissions', $user);

        return view('admin.users.permissions', [
            'user' => $user->load('role.permissions', 'permissionOverrides'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('slug')->get()->groupBy('module'),
            'rolePermissions' => $user->role?->permissions->pluck('slug')->all() ?? [],
            'overrides' => $user->permissionOverrides->mapWithKeys(fn ($p) => [$p->slug => $p->pivot->granted ? 'grant' : 'revoke'])->all(),
        ]);
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $this->authorize('managePermissions', $user);
        $data = $request->validate([
            'overrides' => ['array'],
            'overrides.*' => ['in:inherit,grant,revoke'],
        ]);
        $this->users->syncPermissionOverrides($user, $data['overrides'] ?? []);

        return redirect()->route('admin.users.show', $user)->with('success', 'Permisos individuales actualizados.');
    }

    private function formData(User $user): array
    {
        $roles = Role::query()->orderBy('name')
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', Role::SUPER_ADMIN))
            ->pluck('name', 'id');

        $slug = $user->exists ? $user->role?->slug : null;

        return [
            'user' => $user,
            'roles' => $roles,
            'sectors' => \App\Support\Sectors::all(),
            'assignableSectors' => \App\Support\Sectors::assignableBy(auth()->user()),
            'accessMode' => old('access_mode', ! $user->exists || $slug === \App\Support\Sectors::ROLE ? 'sectors' : ($slug === 'admin' ? 'full' : 'role')),
            'currentSectors' => old('sectors', $slug === \App\Support\Sectors::ROLE ? \App\Support\Sectors::of($user) : []),
            'statuses' => UserStatus::options(),
            'packers' => Packer::query()->where('active', true)->orderBy('last_name')->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->full_name]),
            'owners' => module_enabled('client_portal') ? Owner::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'clients' => module_enabled('client_portal') ? Client::query()->orderBy('business_name')->pluck('business_name', 'id') : collect(),
            'warehouses' => Warehouse::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
