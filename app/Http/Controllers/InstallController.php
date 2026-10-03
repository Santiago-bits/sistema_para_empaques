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
use Illuminate\Support\Facades\Log;
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

    /** Prefijo con el que se apartan las tablas de otro sistema (sin borrarlas). */
    private const ARCHIVE_PREFIX = 'viejo_';

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
            $this->restoreDatabaseSession($request);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

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
     * @return array{ok: bool, needs_tables: bool, message: ?string, foreign?: int}
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
            $ours = Schema::hasTable('migrations') && DB::table('migrations')->where('migration', self::FIRST_MIGRATION)->exists();
            $tables = $ours ? [] : $this->foreignTables();
        } catch (Throwable $e) {
            return ['ok' => false, 'needs_tables' => false, 'message' => 'No se pudo leer la base de datos ('.$this->reason($e).').'];
        }

        if ($tables !== []) {
            return ['ok' => false, 'needs_tables' => false, 'foreign' => count($tables),
                'message' => 'La base de datos configurada ya tiene '.count($tables).' tabla(s) de otro sistema (o de una versión anterior). '
                    .'Para no borrar nada, el instalador no las usa.'];
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

    /**
     * Base con tablas de otro sistema: las renombra con el prefijo «viejo_» (no borra nada, se pueden
     * recuperar renombrándolas de nuevo) y deja la base lista para instalar. Pide la contraseña de la base
     * como prueba de que quien instala es el dueño del servidor (la página es pública hasta instalar).
     */
    public function archive(Request $request): RedirectResponse
    {
        abort_if($this->installed(), 404);
        $request->validate(['db_password' => ['nullable', 'string', 'max:255'], 'confirm' => ['accepted']], [
            'confirm.accepted' => 'Marcá la casilla para confirmar que querés apartar las tablas existentes.',
        ]);

        $expected = (string) config('database.connections.'.config('database.default').'.password');
        if (! hash_equals($expected, (string) $request->input('db_password', ''))) {
            return redirect()->route('install.show')->withErrors(['db_password' => 'La contraseña de la base de datos no es correcta.']);
        }

        $tables = [];
        try {
            $taken = Schema::getTableListing(Schema::getCurrentSchemaListing(), false);
            foreach ($this->foreignTables() as $table) {
                $target = $base = substr(self::ARCHIVE_PREFIX.$table, 0, 60);
                for ($i = 2; in_array($target, $taken, true); $i++) {
                    $target = $base.'_'.$i;
                }
                Schema::rename($table, $target);
                $taken[] = $target;
                $tables[] = $table;
            }
        } catch (Throwable $e) {
            report($e);
            $this->ensureSessionStorage($request);

            return redirect()->route('install.show')->withErrors(['install' => 'No se pudieron apartar las tablas ('.$this->reason($e).').']);
        }
        Log::warning('Instalador: tablas existentes renombradas con el prefijo '.self::ARCHIVE_PREFIX, ['tablas' => $tables, 'ip' => $request->ip()]);
        $this->ensureSessionStorage($request);

        return redirect()->route('install.show')->with('success', 'Listo: se apartaron '.count($tables).' tabla(s) con el prefijo «'
            .self::ARCHIVE_PREFIX.'» (no se borró nada). Ya podés completar la instalación.');
    }

    /**
     * Entre las tablas apartadas suelen estar «sessions» y «cache» del sistema anterior, que esta misma
     * petición usa para guardar la sesión. Se crean ya las tablas del sistema (base vacía) y, si no se puede,
     * la sesión y la caché pasan a archivos: si no, esta y todas las páginas siguientes darían error 500.
     */
    private function ensureSessionStorage(Request $request): void
    {
        @unlink(storage_path('framework/tables.ready'));
        try {
            if ($this->foreignTables() === []) {
                Artisan::call('migrate', ['--force' => true]);
            }
            if (Schema::hasTable('sessions') && Schema::hasTable('cache')) {
                // La sesión se leyó de la tabla vieja: en la nueva hay que insertarla, no actualizarla.
                $handler = $request->hasSession() ? $request->session()->getHandler() : null;
                if ($handler instanceof \Illuminate\Session\DatabaseSessionHandler) {
                    $handler->setExists(false);
                }

                return;
            }
        } catch (Throwable $e) {
            report($e);
        }

        config(['cache.default' => config('cache.default') === 'database' ? 'file' : config('cache.default')]);
        app('cache')->setDefaultDriver(config('cache.default'));
        if ($request->hasSession() && config('session.driver') === 'database') {
            $request->session()->setHandler(app('session')->driver('file')->getHandler());
        }
    }

    /**
     * Con la base vacía esta petición arrancó guardando la sesión en archivos; la próxima ya la buscará en la
     * tabla «sessions». Se guarda ahí desde ahora para que el administrador quede adentro al terminar.
     */
    private function restoreDatabaseSession(Request $request): void
    {
        if (config('session.fallback_from') !== 'database' || ! $request->hasSession() || ! Schema::hasTable('sessions')) {
            return;
        }
        $request->session()->setHandler(app('session')->driver('database')->getHandler());
    }

    /** Tablas de la base configurada (sólo esa, no otras bases del mismo usuario) que no son del sistema ni ya apartadas. */
    private function foreignTables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(Schema::getCurrentSchemaListing(), false),
            fn (string $table) => ! str_starts_with($table, self::ARCHIVE_PREFIX) && $table !== 'sqlite_sequence'
                // Una tabla de migraciones vacía (intento anterior que no llegó a crear nada) no molesta.
                && ! ($table === 'migrations' && DB::table('migrations')->doesntExist()),
        ));
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
