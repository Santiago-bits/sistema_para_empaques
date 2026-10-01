<?php

namespace Tests\Feature\Catalogs;

use App\Catalogs\CatalogRegistry;
use App\Models\Client;
use App\Models\Packer;
use App\Models\Producer;
use App\Models\Season;
use App\Models\Truck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_and_every_catalog_page_render_for_admin(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('catalogs.index'))->assertOk()->assertSee('Productores');

        foreach (CatalogRegistry::all() as $definition) {
            $this->get($definition->route('index'))->assertOk();
            $this->get($definition->route('create'))->assertOk();
        }
    }

    public function test_create_show_edit_toggle_producer(): void
    {
        $this->actingAsRole('admin');

        $this->post(route('catalogs.producers.store'), [
            'code' => 'prod-9', 'name' => 'Finca Test', 'cuit' => '20-12345678-6', 'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $producer = Producer::query()->where('name', 'Finca Test')->firstOrFail();
        $this->assertSame('PROD-9', $producer->code);
        $this->assertSame('20123456786', $producer->cuit);

        $this->get(route('catalogs.producers.show', $producer->id))->assertOk()->assertSee('Finca Test');
        $this->get(route('catalogs.producers.edit', $producer->id))->assertOk();

        $this->put(route('catalogs.producers.update', $producer->id), [
            'code' => 'PROD-9', 'name' => 'Finca Test 2', 'cuit' => '20123456786', 'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Finca Test 2', $producer->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'producer', 'auditable_id' => $producer->id, 'action' => 'update']);

        $this->patch(route('catalogs.producers.toggle', $producer->id))->assertRedirect();
        $this->assertFalse($producer->fresh()->active);
    }

    public function test_invalid_cuit_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('catalogs.clients.store'), [
            'business_name' => 'Cliente SA', 'cuit' => '20-12345678-0', 'tax_condition' => 'RI', 'active' => '1',
        ])->assertSessionHasErrors('cuit');
        $this->assertSame(0, Client::query()->count());
    }

    public function test_truck_plate_is_normalized_and_unique(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('catalogs.trucks.store'), ['plate' => 'ab 123 cd', 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(Truck::query()->where('plate', 'AB123CD')->exists());

        $this->post(route('catalogs.trucks.store'), ['plate' => 'AB123CD', 'active' => '1'])->assertSessionHasErrors('plate');
    }

    public function test_only_one_current_season(): void
    {
        $this->actingAsRole('admin');
        $first = Season::query()->where('is_current', true)->firstOrFail();

        $this->post(route('catalogs.seasons.store'), [
            'name' => 'Temporada Nueva', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'is_current' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Season::query()->where('is_current', true)->count());
        $this->assertFalse($first->fresh()->is_current);
    }

    public function test_packer_code_is_suggested_and_badge_prints(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('packers.create'))->assertOk()->assertSee('EMB001');

        $this->post(route('packers.store'), ['code' => 'emb001', 'first_name' => 'Juan', 'last_name' => 'Pérez', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $packer = Packer::query()->where('code', 'EMB001')->firstOrFail();

        $this->get(route('packers.show', $packer->id))->assertOk()->assertSee('Cajones hoy');
        $this->get(route('packers.badge', $packer))->assertOk()->assertSee('EMB001')->assertSee('<svg', false);
        $this->get(route('packers.badges', ['ids' => [$packer->id]]))->assertOk();
    }

    public function test_intake_operator_can_view_but_not_manage(): void
    {
        $this->actingAsRole('intake_operator');
        $this->get(route('catalogs.producers.index'))->assertOk();
        $this->get(route('catalogs.producers.create'))->assertForbidden();
        $this->post(route('catalogs.producers.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    public function test_packer_role_cannot_see_catalogs(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('catalogs.index'))->assertForbidden();
        $this->get(route('catalogs.clients.index'))->assertForbidden();
    }

    public function test_cannot_delete_producer_in_use(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'P1']);
        \App\Models\Lot::query()->create(['code' => 'L1', 'date' => today(), 'producer_id' => $producer->id, 'status' => 'open']);

        $this->delete(route('catalogs.producers.destroy', $producer->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($producer);
    }
}
