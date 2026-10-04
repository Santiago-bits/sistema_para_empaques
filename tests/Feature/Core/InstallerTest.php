<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Instalador con la base vacía (crea las tablas solo, sin consola) y con una base de otro sistema
 * (mensaje claro, no toca nada). Sin RefreshDatabase: arranca con la base en memoria vacía.
 */
class InstallerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Con MySQL la base de tests es compartida: los tests anteriores la dejan con tablas. El instalador arranca vacío.
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            Artisan::call('db:wipe', ['--force' => true]);
        }
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:reset', ['--force' => true]);
        foreach (['users', 'migrations'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Empaque del Valle', 'warehouse_name' => 'Galpón A', 'admin_first_name' => 'Ana', 'admin_last_name' => 'Pérez',
            'admin_username' => 'ana', 'admin_password' => 'Clave1234', 'admin_password_confirmation' => 'Clave1234',
            'currency' => 'ARS', 'timezone' => 'America/Argentina/Mendoza', 'modules' => ['loads', 'treasury'],
            'weight_min' => '5', 'weight_max' => '30', 'target_daily_kg' => '10000',
        ], $overrides);
    }

    public function test_installs_on_an_empty_database_creating_the_tables(): void
    {
        $this->assertFalse(Schema::hasTable('users'));

        $page = $this->get(route('install.show'))->assertOk();
        // Nunca se muestran datos de conexión.
        $page->assertDontSee(config('database.connections.'.config('database.default').'.database'), false)
            ->assertDontSee('.env', false)->assertSee('Zona horaria');

        $this->post(route('install.store'), $this->payload())->assertRedirect(route('home'));

        $this->assertTrue(Schema::hasTable('users'));
        $admin = User::query()->where('username', 'ana')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertSame('America/Argentina/Mendoza', setting('regional.timezone'));
        $this->assertAuthenticatedAs($admin);

        // Ya instalado: el asistente desaparece.
        $this->get(route('install.show'))->assertRedirect(route('login'));
    }

    public function test_database_of_another_system_is_reported_and_left_untouched(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        DB::table('users')->insert(['nombre' => 'usuario viejo']);

        $this->get(route('install.show'))->assertOk()->assertSee('de otro sistema');
        $this->post(route('install.store'), $this->payload())
            ->assertRedirect(route('install.show'))
            ->assertSessionHasErrors('install');

        // No se borró ni migró nada.
        $this->assertSame('usuario viejo', DB::table('users')->value('nombre'));
        $this->assertFalse(Schema::hasTable('roles'));
    }

    public function test_old_tables_can_be_set_aside_with_the_database_password_and_then_install(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('empaques', fn (Blueprint $table) => $table->id());
        DB::table('users')->insert(['nombre' => 'usuario viejo']);
        config(['database.connections.'.config('database.default').'.password' => 'secreta']);

        $this->get(route('install.show'))->assertOk()->assertSee('Apartar tablas y continuar')->assertSee('2 tabla(s)', false);

        // Sin confirmar o con otra contraseña no toca nada.
        $this->post(route('install.archive'), ['db_password' => 'secreta'])->assertSessionHasErrors('confirm');
        $this->post(route('install.archive'), ['db_password' => 'otra', 'confirm' => 1])->assertSessionHasErrors('db_password');
        $this->assertTrue(Schema::hasTable('empaques'));

        $this->post(route('install.archive'), ['db_password' => 'secreta', 'confirm' => 1])
            ->assertRedirect(route('install.show'))->assertSessionHas('success');
        // Nada se borró: quedaron renombradas.
        $this->assertSame('usuario viejo', DB::table('viejo_users')->value('nombre'));
        $this->assertTrue(Schema::hasTable('viejo_empaques'));

        $this->get(route('install.show'))->assertOk()->assertDontSee('No se puede instalar todavía');
        $this->post(route('install.store'), $this->payload())->assertRedirect(route('home'));
        $this->assertTrue(User::query()->where('username', 'ana')->exists());

        // Instalado: la acción desaparece.
        $this->post(route('install.archive'), ['db_password' => 'secreta', 'confirm' => 1])->assertNotFound();
        Schema::dropIfExists('viejo_users');
        Schema::dropIfExists('viejo_empaques');
    }

    /** Caso real en Hostinger: las tablas viejas incluían «sessions» y «cache», en uso por la misma petición. */
    public function test_setting_aside_old_session_and_cache_tables_keeps_the_site_working(): void
    {
        // Índices como los de Laravel sólo en MySQL: en SQLite sus nombres son globales y chocarían con los nuevos.
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        Schema::create('sessions', function (Blueprint $table) use ($mysql) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            if ($mysql) {
                $table->index('user_id');
                $table->index('last_activity');
            }
        });
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('empaques', fn (Blueprint $table) => $table->id());
        config(['session.driver' => 'database', 'cache.default' => 'database']);

        $this->get(route('install.show'))->assertOk()->assertSee('Apartar tablas y continuar');
        $this->post(route('install.archive'), ['db_password' => '', 'confirm' => 1])
            ->assertRedirect(route('install.show'))->assertSessionHas('success');

        $this->assertTrue(Schema::hasTable('viejo_sessions'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('roles')); // ya quedaron creadas las tablas del sistema

        $this->get(route('install.show'))->assertOk()->assertDontSee('No se puede instalar todavía');
        $this->post(route('install.store'), $this->payload())->assertRedirect(route('home'));
        $this->assertTrue(User::query()->where('username', 'ana')->exists());

        foreach (['viejo_sessions', 'viejo_cache', 'viejo_empaques'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** «Sesión expirada» (419) en el instalador: vuelve al formulario con lo cargado, o explica la causa. */
    public function test_expired_form_returns_to_the_installer_instead_of_419(): void
    {
        $render = function (bool $withCookie) {
            $request = \Illuminate\Http\Request::create(route('install.store'), 'POST', ['company_name' => 'Empaque del Valle', 'admin_password' => 'Clave1234']);
            if ($withCookie) {
                $request->cookies->set(config('session.cookie'), 'algo');
            }
            $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
            $request->setLaravelSession(app('session.store'));
            $this->app->instance('request', $request);

            return app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, new \Illuminate\Session\TokenMismatchException);
        };

        $response = $render(true);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('install.show'), $response->headers->get('Location'));
        $this->assertSame('Empaque del Valle', session()->getOldInput('company_name'));
        $this->assertNull(session()->getOldInput('admin_password'));

        config(['session.secure' => true]);
        $response = $render(false);
        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('https://', $response->getContent());
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->post(route('install.store'), $this->payload(['timezone' => 'Marte/Olympus']))->assertSessionHasErrors('timezone');
        $this->assertFalse(Schema::hasTable('roles'));
    }
}
