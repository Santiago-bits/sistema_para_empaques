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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Asistente de configuración inicial. Sólo está disponible mientras no exista ningún usuario: una vez
 * instalado, estas rutas devuelven 404. Si la base está vacía crea las tablas (no hace falta consola/SSH).
 * Nunca muestra datos de conexión (servidor, base, usuario): son parte del .env y de la seguridad del servidor.
 */
class InstallController extends Controller
{
    /** Primera migración del sistema: si hay tablas pero no está registrada, la base es de otro sistema. */
    private const FIRST_MIGRATION = '2026_01_01_000100_create_core_tables';

    public function show(): View|RedirectResponse
    {
        if ($this->installed()) {
            return redirect()->route('login');
        }

        return view('install.wizard', [
            'database' => $this->databaseStatus(),
            'modules' => collect(ModuleService::CATALOG)->reject(fn ($m) => $m[3]),
            'roles' => config('permissions.roles'),
            'timezones' => self::timezones(),
        ]);
    }

    public function store(Request $request, SettingsService $settings): RedirectResponse
    {
        $database = $this->databaseStatus();
        if (! $database['ok']) {
            return $this->failed($request, $database['message']);
        }
        // Falla CERRADO: con usuarios ya creados el instalador no existe.
        abort_if($this->installed(), 404);

        $data = $this->validated($request);

        // Un solo instalador a la vez (archivo de bloqueo: la caché puede no existir todavía).
        $lock = fopen(storage_path('framework/install.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->failed($request, 'La instalación ya está en curso en otra pestaña o computadora. Esperá unos segundos.');
        }

        try {
            if ($database['needs_tables']) {
                try {
                    Artisan::call('migrate', ['--force' => true]);
                } catch (Throwable $e) {
                    report($e);

                    return $this->failed($request, 'No se pudieron crear las tablas de la base de datos ('.$this->reason($e).').');
                }
            }
            abort_if($this->installed(), 404);
            $this->install($data, $settings);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        @file_put_contents(storage_path('framework/tables.ready'), now()->toIso8601String());
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Instalación completada. ¡Bienvenido!');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
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
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
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
        ], [], [
            'company_name' => 'nombre de la empresa', 'warehouse_name' => 'nombre del galpón', 'admin_first_name' => 'nombre',
            'admin_last_name' => 'apellido', 'admin_username' => 'usuario', 'admin_password' => 'contraseña', 'timezone' => 'zona horaria',
            'weight_min' => 'peso mínimo', 'weight_max' => 'peso máximo', 'target_daily_kg' => 'objetivo diario',
        ]);
    }

    private function install(array $data, SettingsService $settings): void
    {
        DB::transaction(function () use ($data, $settings) {
            abort_if(User::query()->lockForUpdate()->exists(), 404);
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
            $settings->set('regional.timezone', $data['timezone']);
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
    }

    /**
     * Estado de la base SIN exponer datos de conexión.
     *
     * @return array{ok: bool, needs_tables: bool, message: ?string}
     */
    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            return ['ok' => false, 'needs_tables' => false, 'message' => 'No se pudo conectar a la base de datos ('.$this->reason($e).'). '
                .'Quien administra el servidor tiene que revisar la configuración de la base.'];
        }

        try {
            $hasUsers = Schema::hasTable('users');
            $ours = Schema::hasTable('migrations') && DB::table('migrations')->where('migration', self::FIRST_MIGRATION)->exists();
            $tables = count(Schema::getTables());
        } catch (Throwable $e) {
            return ['ok' => false, 'needs_tables' => false, 'message' => 'No se pudo leer la base de datos ('.$this->reason($e).').'];
        }

        if (($hasUsers || $tables > 1) && ! $ours) {
            return ['ok' => false, 'needs_tables' => false,
                'message' => 'La base de datos ya tiene tablas de otro sistema (o de una versión anterior). Para no borrar nada, el instalador no la toca. '
                    .'Hay que usar una base de datos nueva y vacía (en Hostinger: hPanel → Bases de datos → crear una nueva y ponerla en la configuración del servidor).'];
        }

        // Tablas propias creadas: sólo faltan las migraciones nuevas, o nada.
        $pending = ! $ours;
        if ($ours) {
            try {
                $migrator = app('migrator');
                $ran = $migrator->getRepository()->getRan();
                $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
                $pending = array_diff($files, $ran) !== [];
            } catch (Throwable) {
                $pending = false;
            }
        }

        return ['ok' => true, 'needs_tables' => $pending, 'message' => null];
    }

    /** Motivo legible de un error de base de datos (sin datos de conexión). */
    private function reason(Throwable $e): string
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());
        $message = $e->getMessage();

        return match (true) {
            $code === '1045' || str_contains($message, 'Access denied') => 'usuario o contraseña de la base incorrectos',
            $code === '1049' || str_contains($message, 'Unknown database') => 'la base de datos no existe',
            $code === '2002' || str_contains($message, 'Connection refused') || str_contains($message, 'No such file') => 'el servidor de base de datos no responde',
            $code === '1044' || $code === '1142' => 'el usuario de la base no tiene permisos suficientes',
            default => 'error '.$code,
        };
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        return redirect()->route('install.show')
            ->withInput($request->except(['admin_password', 'admin_password_confirmation', 'users']))
            ->withErrors(['install' => $message]);
    }

    private function installed(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('migrations')
                && DB::table('migrations')->where('migration', self::FIRST_MIGRATION)->exists()
                && User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /** Zonas horarias agrupadas por región, con las de Argentina y la región primero. */
    public static function timezones(): array
    {
        $common = ['America/Argentina/Buenos_Aires', 'America/Argentina/Cordoba', 'America/Argentina/Mendoza', 'America/Argentina/Salta',
            'America/Argentina/Tucuman', 'America/Argentina/Ushuaia', 'America/Santiago', 'America/Montevideo', 'America/Asuncion',
            'America/Sao_Paulo', 'America/La_Paz', 'America/Lima', 'America/Bogota', 'America/Mexico_City', 'Europe/Madrid', 'UTC'];
        $options = ['Frecuentes' => array_combine($common, array_map(fn ($tz) => str_replace('_', ' ', $tz), $common))];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $region = str_contains($tz, '/') ? explode('/', $tz)[0] : 'Otras';
            $options[$region][$tz] = str_replace('_', ' ', $tz);
        }

        return $options;
    }
}
