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

        $this->get(route('install.show'))->assertOk()->assertSee('tablas de otro sistema');
        $this->post(route('install.store'), $this->payload())
            ->assertRedirect(route('install.show'))
            ->assertSessionHasErrors('install');

        // No se borró ni migró nada.
        $this->assertSame('usuario viejo', DB::table('users')->value('nombre'));
        $this->assertFalse(Schema::hasTable('roles'));
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->post(route('install.store'), $this->payload(['timezone' => 'Marte/Olympus']))->assertSessionHasErrors('timezone');
        $this->assertFalse(Schema::hasTable('roles'));
    }
}
