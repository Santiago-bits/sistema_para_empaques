<?php

namespace Tests\Feature\Core;

use App\Services\DatabaseUpgrader;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * En Hostinger el deploy por Git no corre las migraciones: después de actualizar, las pantallas nuevas fallaban por
 * tablas o columnas faltantes. Ahora la primera visita pone la base al día sola (y hay un botón por las dudas).
 */
class DatabaseUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemSeeder::class);
        @unlink(storage_path('framework/schema.ok'));
        @unlink(storage_path('framework/upgrade.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/schema.ok'));
        @unlink(storage_path('framework/upgrade.json'));
        parent::tearDown();
    }

    public function test_first_visit_after_an_update_applies_pending_migrations(): void
    {
        $admin = $this->actingAsRole('admin');
        // Simula un servidor con el código nuevo y la base vieja (sin las tablas ni los permisos de la última versión).
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        \App\Models\Permission::query()->whereIn('slug', ['dtv.view', 'dtv.manage'])->delete();
        @unlink(storage_path('framework/schema.ok'));
        $this->assertFalse(Schema::hasTable('dtv_documents'));
        $this->assertNotSame([], app(DatabaseUpgrader::class)->pending());

        $this->get(route('lots.index'))->assertOk();

        $this->assertTrue(Schema::hasTable('dtv_documents'));
        $this->assertTrue(Schema::hasColumn('lots', 'bins'));
        $this->assertSame([], app(DatabaseUpgrader::class)->pending());
        $this->assertTrue(app(DatabaseUpgrader::class)->isUpToDate());
        $this->assertTrue(app(DatabaseUpgrader::class)->lastResult()['ok']);

        // Los permisos nuevos también llegaron: el dueño del galpón ve la sección nueva.
        $this->assertTrue($admin->fresh()->can('dtv.view'));
        $this->get(route('dtv.index'))->assertOk();
    }

    public function test_super_admin_sees_the_warning_and_can_upgrade_by_hand(): void
    {
        $this->actingAsRole('super_admin');
        $this->post(route('superadmin.confirm.store'), ['password' => 'password']);
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        // Un intento automático que falló hace un rato: no se reintenta solo, se muestra el aviso.
        file_put_contents(storage_path('framework/upgrade.json'), json_encode(['ok' => false, 'at' => time(), 'error' => 'Prueba de error']));
        @unlink(storage_path('framework/schema.ok'));

        $this->get(route('superadmin.index'))->assertOk()->assertSee('La base de datos no está al día')->assertSee('Prueba de error');
        $this->assertFalse(Schema::hasTable('dtv_documents'));

        $this->post(route('superadmin.upgrade'))->assertRedirect(route('superadmin.index'))->assertSessionHas('success');
        $this->assertTrue(Schema::hasTable('dtv_documents'));
        $this->get(route('superadmin.index'))->assertDontSee('La base de datos no está al día');
    }
}
