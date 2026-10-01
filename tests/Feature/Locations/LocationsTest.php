<?php

namespace Tests\Feature\Locations;

use App\Enums\CrateStatus;
use App\Models\LocationMovement;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function location(string $code, ?int $parent = null, int $capacity = 10, string $type = 'sector'): WarehouseLocation
    {
        return WarehouseLocation::query()->create([
            'code' => $code, 'name' => 'Ubicación '.$code, 'type' => $type, 'parent_id' => $parent, 'capacity_pallets' => $capacity,
            'map_x' => 1, 'map_y' => 1, 'map_w' => 3, 'map_h' => 2,
        ]);
    }

    public function test_pages_render(): void
    {
        $this->actingAsRole('admin');
        $sector = $this->location('S-A');
        $this->location('A01', $sector->id, 5, 'position');

        foreach (['locations.index', 'locations.map', 'locations.movements', 'locations.move.create', 'locations.create'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('locations.show', $sector))->assertOk();
        $this->get(route('locations.edit', $sector))->assertOk();
        $this->getJson(route('locations.content', $sector))->assertOk()->assertJsonPath('name', 'Ubicación S-A');
    }

    public function test_create_location(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('locations.store'), ['type' => 'cold_room', 'code' => 'CAM-9', 'name' => 'Cámara 9', 'capacity_pallets' => 20, 'active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouse_locations', ['code' => 'CAM-9']);
    }

    public function test_move_pallet_moves_crates_and_records_journey(): void
    {
        $this->actingAsRole('intake_operator');
        $from = $this->location('S-A');
        $to = $this->location('S-B');
        $pallet = $this->pallet('PAL-1', 'with_product');
        $pallet->update(['location_id' => $from->id]);
        $crate = $this->crate('CJ-1', CrateStatus::Processed, ['pallet_id' => $pallet->id, 'location_id' => $from->id]);

        $this->postJson(route('locations.move'), ['movable_type' => 'pallet', 'movable_id' => $pallet->id, 'to_location_id' => $to->id])
            ->assertOk();

        $this->assertSame($to->id, $pallet->fresh()->location_id);
        $this->assertSame($to->id, $crate->fresh()->location_id);
        $this->assertDatabaseHas('location_movements', ['movable_type' => 'pallet', 'movable_id' => $pallet->id, 'from_location_id' => $from->id, 'to_location_id' => $to->id]);

        $this->get(route('locations.movements', ['code' => 'PAL-1']))->assertOk()->assertSee('Recorrido');
        $this->getJson(route('locations.lookup', ['code' => 'PAL-1']))->assertJsonPath('kind', 'movable');
        $this->getJson(route('locations.lookup', ['code' => 'S-B', 'kind' => 'location']))->assertJsonPath('kind', 'location');
    }

    public function test_map_update_and_capacity(): void
    {
        $this->actingAsRole('admin');
        $sector = $this->location('S-A', null, 20);
        $this->putJson(route('locations.map.update'), ['positions' => [['id' => $sector->id, 'x' => 4, 'y' => 2, 'w' => 5, 'h' => 3]]])->assertOk();
        $this->assertSame(4, $sector->fresh()->map_x);

        $pallet = $this->pallet('PAL-1', 'with_product');
        $pallet->update(['location_id' => $sector->id]);
        $summary = app(\App\Services\LocationService::class)->capacitySummary();
        $this->assertSame(1, $summary['pallets']);
    }

    public function test_permissions(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('locations.index'))->assertForbidden();

        $this->actingAsRole('loads_operator');
        $this->get(route('locations.index'))->assertOk();
        $this->get(route('locations.create'))->assertForbidden();
    }
}
