<?php

namespace Tests\Feature\MultiWarehouse;

use App\Http\Middleware\EnsureSuperAdminConfirmed;
use App\Models\Module;
use App\Models\Reason;
use App\Models\Role;
use App\Models\Sequence;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CurrentWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Administración general → Galpones: el Super Administrador crea galpones y su administrador general. */
class WarehousesPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::factory()->role(Role::SUPER_ADMIN)->create();
        $this->actingAs($super)->withSession([EnsureSuperAdminConfirmed::SESSION_KEY => time()]);

        return $super;
    }

    public function test_super_admin_creates_a_warehouse_with_its_admin_who_only_sees_his_warehouse(): void
    {
        $super = $this->superAdmin();
        $this->assertNull($super->warehouse_id);
        $first = Warehouse::query()->value('id');

        $this->get(route('superadmin.warehouses.index'))->assertOk()->assertSee('Nuevo galpón');
        $this->get(route('superadmin.warehouses.create'))->assertOk();
        $this->post(route('superadmin.warehouses.store'), ['name' => 'Empaque del Norte', 'cuit' => '30-50000173-5', 'locality' => 'Cipolletti', 'active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $north = Warehouse::query()->where('name', 'Empaque del Norte')->sole();
        $this->assertMatchesRegularExpression('/^GAL-\d{4}$/', $north->code);

        // Nace con sus propios datos base y su configuración (sin tocar los del primer galpón).
        CurrentWarehouse::runFor($north->id, function () {
            $this->assertSame('Empaque del Norte', setting('company.name'));
            $this->assertSame('30500001735', setting('company.cuit'));
            $this->assertGreaterThan(0, Module::query()->count());
            $this->assertGreaterThan(0, Reason::query()->count());
            $this->assertGreaterThan(0, Sequence::query()->count());
        });
        CurrentWarehouse::runFor($first, fn () => $this->assertNotSame('Empaque del Norte', setting('company.name')));

        // Su administrador general: contraseña temporal, en su galpón.
        $this->get(route('superadmin.warehouses.show', $north))->assertOk()->assertSee('Todavía no tiene administrador.')->assertSee('Crear administrador');
        $this->post(route('superadmin.warehouses.admin', $north), ['first_name' => 'Nora', 'last_name' => 'Norte', 'username' => 'admin_norte',
            'password' => 'temporal123'])->assertRedirect()->assertSessionHas('success');
        $admin = User::query()->where('username', 'admin_norte')->sole();
        $this->assertSame($north->id, $admin->warehouse_id);
        $this->assertTrue($admin->must_change_password);
        $this->assertSame(Role::ADMIN, $admin->role->slug);

        // El admin del galpón nuevo entra y ve sólo su galpón: no ve a los usuarios del otro, ni la administración general.
        $other = User::factory()->role('supervisor')->inWarehouse($first)->create(['first_name' => 'Pedro', 'last_name' => 'DelSur']);
        $admin->forceFill(['must_change_password' => false])->save();
        $this->actingAs($admin);
        $this->assertSame($north->id, CurrentWarehouse::id());
        $this->get(route('dashboard'))->assertOk()->assertSee('Empaque del Norte');
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('DelSur');
        $this->get(route('admin.users.show', $other))->assertNotFound();
        $this->get(route('superadmin.warehouses.index'))->assertNotFound();
        $this->post(route('warehouses.enter', $first))->assertNotFound();

        // Carga a un empleado: queda en su galpón.
        $this->post(route('admin.users.store'), [
            'first_name' => 'Ema', 'last_name' => 'Empleada', 'username' => 'ema_norte', 'status' => 'active', 'access_mode' => 'role',
            'role_id' => Role::query()->where('slug', 'supervisor')->value('id'), 'password' => 'clave1234', 'password_confirmation' => 'clave1234',
        ]);
        $this->assertSame([], session('errors')?->getBag('default')->toArray() ?? []);
        $this->assertSame($north->id, User::query()->where('username', 'ema_norte')->value('warehouse_id'));
    }

    public function test_super_admin_switches_warehouse_and_a_suspended_warehouse_cannot_log_in(): void
    {
        $super = $this->superAdmin();
        $first = Warehouse::query()->sole();
        $second = app(\App\Services\WarehouseService::class)->create(['name' => 'Galpón Dos'], $super);

        $this->get(route('dashboard'))->assertOk()->assertSee('data-current-warehouse', false);
        $this->post(route('warehouses.enter', $second))->assertRedirect(route('home'));
        $this->assertSame($second->id, session('warehouse_id'));

        // Suspender: sus usuarios no pueden entrar (los datos se conservan).
        $worker = User::factory()->role('supervisor')->inWarehouse($second->id)->create(['username' => 'trabajador']);
        $this->put(route('superadmin.warehouses.update', $second), ['name' => 'Galpón Dos', 'active' => 0])->assertRedirect();
        $this->assertFalse($second->fresh()->active);
        auth()->logout();
        $this->post(route('login'), ['login' => 'trabajador', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->actingAs($worker)->get(route('dashboard'))->assertRedirect(route('login'));

        // El único galpón activo no se puede suspender.
        $this->superAdmin();
        $this->put(route('superadmin.warehouses.update', $first), ['name' => $first->name, 'active' => 0])->assertSessionHasErrors('active');
        $this->assertTrue($first->fresh()->active);
    }

    public function test_users_tab_shows_one_card_per_warehouse_and_then_its_users(): void
    {
        $super = $this->superAdmin();
        $first = Warehouse::query()->sole();
        $second = app(\App\Services\WarehouseService::class)->create(['name' => 'Galpón Dos'], $super);
        User::factory()->role('supervisor')->inWarehouse($first->id)->create(['first_name' => 'Ana', 'last_name' => 'DelPrimero']);
        User::factory()->role('supervisor')->inWarehouse($second->id)->create(['first_name' => 'Beto', 'last_name' => 'DelSegundo', 'must_change_password' => true]);

        // Primero los cuadros: un galpón por cuadro y los Super Administradores aparte; todavía ningún usuario.
        $this->get(route('superadmin.users.index'))->assertOk()
            ->assertSee($first->name)->assertSee('Galpón Dos')->assertSee('Super Administradores')
            ->assertSee('data-warehouse-card', false)->assertDontSee('DelPrimero')->assertDontSee('DelSegundo');

        // Al entrar a un galpón: sólo sus usuarios.
        $this->get(route('superadmin.users.index', ['warehouse' => $second->id]))->assertOk()
            ->assertSee('Usuarios de Galpón Dos')->assertSee('DelSegundo')->assertDontSee('DelPrimero');
        $this->get(route('superadmin.users.index', ['warehouse' => $first->id, 'filter' => 'temporary']))->assertOk()
            ->assertDontSee('DelSegundo')->assertDontSee('DelPrimero');
        $this->get(route('superadmin.users.index', ['warehouse' => 'super']))->assertOk()
            ->assertSee($super->username)->assertDontSee('DelPrimero');

        // Buscar mira todos los galpones y dice de cuál es cada uno.
        $this->get(route('superadmin.users.index', ['q' => 'Del']))->assertOk()
            ->assertSee('DelPrimero')->assertSee('DelSegundo')->assertSee('<th>Galpón</th>', false);

        $this->get(route('superadmin.users.index', ['warehouse' => 999]))->assertNotFound();
        $this->get(route('superadmin.users.index', ['warehouse' => 'otro']))->assertSessionHasErrors('warehouse');
    }
}
