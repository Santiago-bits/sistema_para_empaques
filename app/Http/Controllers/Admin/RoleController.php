<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', $this->formData(new Role));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'slug' => Str::slug($data['name'], '_'),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('success', "Rol «{$role->name}» creado.");
    }

    public function edit(Role $role): View
    {
        abort_if($role->slug === Role::SUPER_ADMIN, 403, 'El rol Super Administrador tiene acceso total y no se edita.');

        return view('admin.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === Role::SUPER_ADMIN, 403);
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return redirect()->route('admin.roles.index')->with('success', "Rol «{$role->name}» actualizado.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            throw new BusinessException('Los roles del sistema no se pueden eliminar.');
        }
        if ($role->users()->exists()) {
            throw new BusinessException('No se puede eliminar un rol con usuarios asignados.');
        }
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);
    }

    private function syncPermissions(Role $role, array $slugs): void
    {
        $before = $role->permissions()->pluck('slug')->sort()->values()->all();
        // Un administrador no puede agregar (ni quitar) permisos reservados al super administrador.
        $slugs = Permission::guardProtected($slugs, $before, auth()->user());
        $role->permissions()->sync(Permission::query()->whereIn('slug', $slugs)->pluck('id'));
        $after = collect($slugs)->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->log('permissions', $role, ['permissions' => $before], ['permissions' => $after], 'Permisos del rol actualizados');
        }
    }

    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'permissions' => Permission::query()->orderBy('slug')->get()->groupBy('module'),
            'selected' => old('permissions', $role->exists ? $role->permissions()->pluck('slug')->all() : []),
            'moduleNames' => collect(\App\Services\ModuleService::CATALOG)->map(fn ($m) => $m[0]),
        ];
    }
}
