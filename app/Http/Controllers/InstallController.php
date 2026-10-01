<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ModuleService;
use App\Services\SettingsService;
use Database\Seeders\SystemSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Asistente de configuración inicial. Sólo está disponible mientras no exista
 * ningún usuario: una vez instalado, estas rutas devuelven 404.
 */
class InstallController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if ($this->installed()) {
            return redirect()->route('login');
        }

        $dbOk = true;
        $dbError = null;
        try {
            DB::connection()->getPdo();
            $dbOk = Schema::hasTable('users');
            if (! $dbOk) {
                $dbError = 'La base existe pero faltan las tablas. Ejecutá: php artisan migrate';
            }
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = 'No se pudo conectar a la base de datos. Revisá DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD en el archivo .env.';
        }

        return view('install.wizard', [
            'dbOk' => $dbOk,
            'dbError' => $dbError,
            'db' => [
                'driver' => config('database.default'),
                'host' => config('database.connections.'.config('database.default').'.host'),
                'database' => config('database.connections.'.config('database.default').'.database'),
            ],
            'modules' => collect(ModuleService::CATALOG)->reject(fn ($m) => $m[3]),
            'roles' => config('permissions.roles'),
        ]);
    }

    public function store(Request $request, SettingsService $settings): RedirectResponse
    {
        abort_if($this->installed(), 404);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_cuit' => ['nullable', 'digits:11'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'warehouse_name' => ['required', 'string', 'max:100'],
            'admin_first_name' => ['required', 'string', 'max:80'],
            'admin_last_name' => ['required', 'string', 'max:80'],
            'admin_username' => ['required', 'alpha_dash', 'max:60'],
            'admin_dni' => ['nullable', 'digits_between:7,9'],
            'admin_email' => ['nullable', 'email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            'currency' => ['required', Rule::in(['ARS', 'USD'])],
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(ModuleService::CATALOG))],
            'users' => ['array', 'max:10'],
            'users.*.first_name' => ['required_with:users.*.username', 'nullable', 'string', 'max:80'],
            'users.*.last_name' => ['required_with:users.*.username', 'nullable', 'string', 'max:80'],
            'users.*.username' => ['nullable', 'alpha_dash', 'max:60', 'distinct', 'different:admin_username'],
            'users.*.role' => ['required_with:users.*.username', 'nullable', Rule::in(array_keys(config('permissions.roles')))],
            'users.*.password' => ['required_with:users.*.username', 'nullable', Password::defaults()],
            'weight_min' => ['required', 'numeric', 'min:0'],
            'weight_max' => ['required', 'numeric', 'gt:weight_min'],
            'target_daily_kg' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $settings) {
            (new SystemSeeder)->run();

            $company = Company::query()->firstOrFail();
            $company->update(['name' => $data['company_name'], 'cuit' => $data['company_cuit'] ?? null]);
            $warehouse = Warehouse::query()->firstOrFail();
            $warehouse->update(['name' => $data['warehouse_name']]);

            $settings->set('company.name', $data['company_name']);
            $settings->set('company.cuit', $data['company_cuit'] ?? '');
            $settings->set('company.address', $data['company_address'] ?? '');
            $settings->set('company.phone', $data['company_phone'] ?? '');
            $settings->set('regional.currency', $data['currency']);
            $settings->set('production.weight_min', (float) $data['weight_min']);
            $settings->set('production.weight_max', (float) $data['weight_max']);
            $settings->set('production.target_daily_kg', (float) $data['target_daily_kg']);
            $settings->set('system.installed', true);

            $enabled = $data['modules'] ?? [];
            foreach (Module::query()->where('is_core', false)->get() as $module) {
                $module->update(['enabled' => in_array($module->key, $enabled, true)]);
            }
            app(ModuleService::class)->flush();

            $admin = User::query()->create([
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'username' => $data['admin_username'],
                'dni' => $data['admin_dni'] ?? null,
                'email' => $data['admin_email'] ?? null,
                'role_id' => Role::query()->where('slug', Role::SUPER_ADMIN)->value('id'),
                'status' => 'active',
                'password' => $data['admin_password'],
            ]);
            $admin->warehouses()->sync([$warehouse->id]);

            foreach ($data['users'] ?? [] as $row) {
                if (empty($row['username'])) {
                    continue;
                }
                $user = User::query()->create([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'username' => $row['username'],
                    'role_id' => Role::query()->where('slug', $row['role'])->value('id'),
                    'status' => 'active',
                    'password' => $row['password'],
                ]);
                $user->warehouses()->sync([$warehouse->id]);
            }

            Auth::login($admin);
        });

        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Instalación completada. ¡Bienvenido!');
    }

    private function installed(): bool
    {
        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
