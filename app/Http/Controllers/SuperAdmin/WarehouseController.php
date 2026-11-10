<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Crate;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Administración general → Galpones (sólo el Super Administrador): un solo sistema para muchos galpones.
 * Se crea cada galpón y su administrador general; ese administrador carga a sus empleados y maneja su galpón.
 */
class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouses)
    {
    }

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $adminRole = Role::query()->withoutGlobalScopes()->where('slug', Role::ADMIN)->whereNull('warehouse_id')->value('id');

        $list = Warehouse::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes((string) $term, '%_\\').'%')
                ->orWhere('code', 'like', '%'.addcslashes((string) $term, '%_\\').'%')))
            ->orderBy('name')->get();
        $ids = $list->pluck('id');
        $users = User::query()->whereIn('warehouse_id', $ids)->selectRaw('warehouse_id, COUNT(*) as n')->groupBy('warehouse_id')->pluck('n', 'warehouse_id');
        $admins = User::query()->whereIn('warehouse_id', $ids)->where('role_id', $adminRole)->orderBy('id')->get(['id', 'warehouse_id', 'username'])->groupBy('warehouse_id');
        $crates = Crate::query()->withoutGlobalScopes()->whereIn('warehouse_id', $ids)->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('warehouse_id, COUNT(*) as n')->groupBy('warehouse_id')->pluck('n', 'warehouse_id');

        return view('superadmin.warehouses.index', [
            'warehouses' => $list, 'users' => $users, 'admins' => $admins, 'crates' => $crates,
            'current' => CurrentWarehouse::id(),
        ]);
    }

    public function create(): View
    {
        return view('superadmin.warehouses.form', ['warehouse' => new Warehouse(['active' => true]), 'nextCode' => $this->warehouses->nextCode(),
            'associations' => \App\Models\Association::query()->where('active', true)->orderBy('name')->get(), 'linked' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $warehouse = $this->warehouses->create($data, $request->user());

        return redirect()->route('superadmin.warehouses.show', $warehouse)
            ->with('success', 'Galpón creado. Ahora creale su administrador general: él va a cargar a sus empleados.');
    }

    public function show(Warehouse $warehouse): View
    {
        $adminRole = Role::query()->withoutGlobalScopes()->where('slug', Role::ADMIN)->whereNull('warehouse_id')->value('id');

        return view('superadmin.warehouses.show', [
            'warehouse' => $warehouse->load('associations'),
            'admins' => User::query()->where('warehouse_id', $warehouse->id)->where('role_id', $adminRole)->orderBy('id')->get(),
            'usersCount' => User::query()->where('warehouse_id', $warehouse->id)->count(),
            'crates30' => Crate::query()->withoutGlobalScopes()->where('warehouse_id', $warehouse->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'settings' => CurrentWarehouse::runFor($warehouse->id, fn () => ['cuit' => setting('company.cuit'), 'arca' => setting('arca.warehouse_code'), 'locality' => setting('company.locality')]),
            'password' => WarehouseService::temporaryPassword(),
        ]);
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('superadmin.warehouses.form', ['warehouse' => $warehouse, 'nextCode' => $warehouse->code,
            'associations' => \App\Models\Association::query()->where(fn ($q) => $q->where('active', true)->orWhereHas('warehouses', fn ($w) => $w->whereKey($warehouse->id)))->orderBy('name')->get(),
            'linked' => $warehouse->associations()->pluck('associations.id')->all()]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $this->validated($request, $warehouse);
        if (! ($data['active'] ?? false) && (int) $warehouse->id === (int) Warehouse::query()->where('active', true)->orderBy('id')->value('id')
            && Warehouse::query()->where('active', true)->count() === 1) {
            return back()->withErrors(['active' => 'Es el único galpón activo: no se puede suspender.']);
        }
        $this->warehouses->update($warehouse, $data);

        return redirect()->route('superadmin.warehouses.show', $warehouse)
            ->with('success', $warehouse->active ? 'Galpón actualizado.' : 'Galpón suspendido: sus usuarios no pueden entrar. Sus datos se conservan.');
    }

    public function storeAdmin(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [], ['first_name' => 'nombre', 'last_name' => 'apellido', 'username' => 'usuario', 'password' => 'contraseña temporal']);

        $user = $this->warehouses->createAdmin($warehouse, $data, $request->user());

        return redirect()->route('superadmin.warehouses.show', $warehouse)->with('success',
            'Administrador creado. Pasale al encargado del galpón: usuario «'.$user->username.'» y contraseña temporal «'.$data['password']
            .'» (al entrar la primera vez el sistema le pide cambiarla).');
    }

    /** El Super Administrador entra a trabajar en un galpón (se recuerda en su sesión). */
    public function enter(Request $request, Warehouse $warehouse): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 404);
        CurrentWarehouse::set($warehouse->id);

        return redirect()->route('home')->with('success', 'Estás trabajando en el galpón «'.$warehouse->name.'».');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'cuit' => ['nullable', new \App\Rules\Cuit],
            'address' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
            'associations' => ['nullable', 'array'],
            'associations.*' => ['integer', Rule::exists('associations', 'id')],
        ], [], ['associations' => 'asociaciones', 'name' => 'nombre del galpón', 'code' => 'código', 'address' => 'dirección', 'locality' => 'localidad']);
    }
}
