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
            // El rol del Super Administrador (dueño del sistema) sólo lo ve otro Super Administrador.
            // Los usuarios se cuentan sólo los del galpón: los roles del sistema los comparten todos.
            'roles' => Role::query()->withCount(['users' => fn ($q) => $q->visibleTo(), 'permissions'])
                ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('slug', '!=', Role::SUPER_ADMIN))->orderBy('name')->get(),
        ]);
    }

    /** «Nuevo rol», o una copia de otro (?from=ID) para ajustar un rol del sistema a este galpón. */
    public function create(Request $request): View
    {
        $role = new Role;
        $source = $request->filled('from') ? Role::query()->where('slug', '!=', Role::SUPER_ADMIN)->find($request->integer('from')) : null;
        if ($source) {
            $role->name = Str::limit($source->name.' (copia)', 80, '');
            $role->description = $source->description;
        }
        $data = $this->formData($role);
        if ($source && ! old('permissions')) {
            $data['selected'] = $source->permissions()->pluck('slug')->all();
        }

        return view('admin.roles.form', $data + ['source' => $source]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = DB::transaction(function () use ($data) {
            // Los roles de un galpón llevan su número adelante: nunca chocan con los del sistema ni con los de otro galpón.
            $base = (\App\Support\Tenancy::ready() && \App\Support\CurrentWarehouse::id() ? 'g'.\App\Support\CurrentWarehouse::id().'_' : '')
                .(Str::slug($data['name'], '_') ?: 'rol');
            // Dos nombres distintos pueden dar la misma clave («Jefe de línea» y «jefe-de-linea»): se numera.
            $slug = $base;
            for ($i = 2; Role::query()->withoutGlobalScopes()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'_'.$i;
            }
            $role = Role::query()->create([
                'slug' => $slug,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('success', "Rol «{$role->name}» creado.");
    }

    public function edit(Role $role): View|RedirectResponse
    {
        abort_if($role->slug === Role::SUPER_ADMIN, 403, 'El rol Super Administrador tiene acceso total y no se edita.');
        if ($this->isShared($role)) {
            // Es de todos los galpones: se ofrece una copia propia para cambiarla.
            return redirect()->route('admin.roles.create', ['from' => $role->id])
                ->with('warning', 'El rol «'.$role->name.'» es común a todos los galpones y sólo lo cambia el Super Administrador. Guardá esta copia con tus cambios y asignásela a tu gente.');
        }

        return view('admin.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === Role::SUPER_ADMIN, 403);
        // Un galpón no cambia los roles que comparten todos los galpones (dejaría sin permisos a los demás).
        abort_if($this->isShared($role), 403, 'Este rol es común a todos los galpones: hacé una copia para tu galpón.');
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
            // Único entre los roles que ve este galpón (los del sistema y los suyos), no contra los de otros galpones.
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)
                ->where(fn ($q) => \App\Support\CurrentWarehouse::id()
                    ? $q->where(fn ($w) => $w->whereNull('warehouse_id')->orWhere('warehouse_id', \App\Support\CurrentWarehouse::id()))
                    : $q)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);
    }

    private function syncPermissions(Role $role, array $slugs): void
    {
        $before = $role->permissions()->pluck('slug')->sort()->values()->all();
        // Nadie da permisos que no tiene; y un administrador no agrega (ni quita) los reservados al super administrador.
        Permission::assertCanGrant($slugs, $before, auth()->user());
        $slugs = Permission::guardProtected($slugs, $before, auth()->user());
        $role->permissions()->sync(Permission::query()->whereIn('slug', $slugs)->pluck('id'));
        $after = collect($slugs)->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->log('permissions', $role, ['permissions' => $before], ['permissions' => $after], 'Permisos del rol actualizados');
        }
    }

    /** Rol del sistema (sin galpón) visto por alguien que trabaja en un galpón. */
    private function isShared(Role $role): bool
    {
        return $role->warehouse_id === null && auth()->user()?->warehouse_id !== null;
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
