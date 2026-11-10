<?php

namespace Tests\Feature\MultiWarehouse;

use App\Http\Middleware\EnsureSuperAdminConfirmed;
use App\Models\Association;
use App\Models\Pallet;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Support\CurrentWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Asociaciones de citricultores: cada galpón pertenece a una o a varias, y salen en lo que imprime. */
class AssociationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_links_associations_and_each_warehouse_prints_only_its_own(): void
    {
        $super = User::factory()->role(Role::SUPER_ADMIN)->create();
        $this->actingAs($super)->withSession([EnsureSuperAdminConfirmed::SESSION_KEY => time()]);
        $first = Warehouse::query()->sole();

        $this->get(route('superadmin.associations.index'))->assertOk()->assertSee('Nueva asociación');
        $this->post(route('superadmin.associations.store'), ['name' => 'Asociación de Citricultores de Chajarí', 'short_name' => 'Chajarí', 'active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('superadmin.associations.store'), ['name' => 'Asociación de Citricultores de Santa Ana', 'active' => 1])->assertRedirect();
        $this->post(route('superadmin.associations.store'), ['name' => 'Asociación de Citricultores de Chajarí', 'active' => 1])->assertSessionHasErrors('name');
        $chajari = Association::query()->where('short_name', 'Chajarí')->sole();
        $santaAna = Association::query()->where('name', 'like', '%Santa Ana')->sole();

        // El galpón A pertenece a las dos; el B (nuevo) sólo a Santa Ana.
        $this->get(route('superadmin.warehouses.edit', $first))->assertOk()->assertSee('Asociaciones de citricultores');
        $this->put(route('superadmin.warehouses.update', $first), ['name' => $first->name, 'active' => 1, 'associations' => [$chajari->id, $santaAna->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('superadmin.warehouses.store'), ['name' => 'Galpón Beta', 'active' => 1, 'associations' => [$santaAna->id]])->assertRedirect();
        $beta = Warehouse::query()->where('name', 'Galpón Beta')->sole();
        $this->assertSame([$santaAna->id], $beta->associations()->pluck('associations.id')->all());
        $this->get(route('superadmin.warehouses.show', $beta))->assertOk()->assertSee('Asociación de Citricultores de Santa Ana');
        $this->get(route('superadmin.associations.index'))->assertOk()->assertSee('Galpón Beta');

        // Etiquetas del pallet del galpón A: las dos asociaciones.
        CurrentWarehouse::set($first->id);
        $pallet = Pallet::query()->create(['code' => 'PAL-A1', 'status' => 'received', 'received_at' => now()]);
        $this->get(route('labels.pallets', ['ids' => [$pallet->id]]))->assertOk()
            ->assertSee('Asociación de Citricultores de Chajarí · Asociación de Citricultores de Santa Ana');

        // El administrador de B ve sólo la suya, en su configuración y en sus etiquetas.
        $adminB = User::factory()->role('admin')->inWarehouse($beta->id)->create();
        $this->actingAs($adminB);
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Asociación de Citricultores de Santa Ana')->assertDontSee('Chajarí');
        $palletB = Pallet::query()->create(['code' => 'PAL-B1', 'status' => 'received', 'received_at' => now()]);
        $this->get(route('labels.pallets', ['ids' => [$palletB->id]]))->assertOk()
            ->assertSee('Asociación de Citricultores de Santa Ana')->assertDontSee('Chajarí');

        // Desvincular: deja de salir. Una asociación inactiva tampoco sale.
        $this->actingAs($super)->withSession([EnsureSuperAdminConfirmed::SESSION_KEY => time()]);
        $this->put(route('superadmin.associations.update', $santaAna), ['name' => $santaAna->name, 'active' => 0])->assertRedirect();
        CurrentWarehouse::runFor($beta->id, fn () => $this->assertNull(CurrentWarehouse::associationsLine()));
        $this->put(route('superadmin.warehouses.update', $first), ['name' => $first->name, 'active' => 1, 'associations' => [$chajari->id]])->assertRedirect();
        $this->assertSame(['Asociación de Citricultores de Chajarí'], $first->associations()->pluck('name')->all());
    }

    public function test_only_the_super_admin_manages_associations(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('superadmin.associations.index'))->assertNotFound();
    }
}
