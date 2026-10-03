<?php

namespace Tests\Feature\Core;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Super Administrador (dueño del sistema) no es empleado del galpón: el dueño del galpón y su personal
 * no lo ven en ningún lado; él sí ve a todos.
 */
class HiddenSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shed_owner_never_sees_the_super_admin(): void
    {
        $super = User::factory()->role('super_admin')->create(['first_name' => 'Leonardo', 'last_name' => 'Zarlenga', 'username' => 'leomover']);
        $this->actingAs($super);
        app(AuditService::class)->log('settings', null, null, ['x' => 1], 'Cambio hecho por el super admin');

        $owner = $this->actingAsRole('admin', ['first_name' => 'Santiago', 'last_name' => 'Jacobo', 'username' => 'santi']);
        app(AuditService::class)->log('settings', null, null, ['x' => 2], 'Cambio hecho por el dueño del galpón');

        // Usuarios, ficha, filtros de rol.
        $this->get(route('admin.users.index'))->assertOk()->assertSee('santi')->assertDontSee('leomover')->assertDontSee('Zarlenga')
            ->assertDontSee('Super Administrador');
        $this->get(route('admin.users.show', $super))->assertForbidden();
        $this->get(route('admin.users.edit', $super))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertOk()->assertDontSee('Super Administrador');

        // Historial de cambios: lo suyo no aparece, ni en la lista ni abriendo el registro.
        $this->get(route('admin.audit.index'))->assertOk()->assertSee('Cambio hecho por el dueño del galpón')
            ->assertDontSee('Cambio hecho por el super admin')->assertDontSee('leomover');
        $hiddenLog = AuditLog::query()->where('description', 'Cambio hecho por el super admin')->firstOrFail();
        $this->get(route('admin.audit.show', $hiddenLog))->assertNotFound();

        // Actividad del personal y búsqueda.
        $this->get(route('admin.activity.index'))->assertOk()->assertDontSee('Zarlenga');
        $this->get(route('search', ['q' => 'leomover']))->assertDontSee('Zarlenga');

        // Si hace falta mostrar quién hizo algo, se ve «Soporte del sistema».
        $this->assertSame(User::HIDDEN_NAME, $super->fresh()->full_name);
        $this->assertSame('Santiago Jacobo', $owner->full_name);
    }

    public function test_the_super_admin_sees_everyone(): void
    {
        $this->actingAsRole('admin', ['username' => 'santi']);
        $super = $this->actingAsRole('super_admin', ['first_name' => 'Leonardo', 'last_name' => 'Zarlenga', 'username' => 'leomover']);

        $this->get(route('admin.users.index'))->assertOk()->assertSee('santi')->assertSee('leomover');
        $this->assertSame('Leonardo Zarlenga', $super->full_name);
    }
}
