<?php

namespace Tests\Feature\Supplies;

use App\Models\Alert;
use App\Models\ColdRoom;
use App\Models\Machine;
use App\Models\Supply;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuppliesMaintenanceColdRoomsTest extends TestCase
{
    use RefreshDatabase;

    private function supply(float $stock = 10, float $min = 5): Supply
    {
        return Supply::query()->create(['code' => 'CAJ', 'name' => 'Caja 18 kg', 'unit' => 'u', 'stock' => $stock, 'min_stock' => $min]);
    }

    public function test_supply_pages_and_movements(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('supplies.index'))->assertOk();
        $this->get(route('supplies.create'))->assertOk();
        $this->post(route('supplies.store'), ['code' => 'ETQ', 'name' => 'Etiquetas', 'unit' => 'rollo', 'stock' => '3', 'min_stock' => '2', 'active' => '1'])
            ->assertSessionHasNoErrors();

        $supply = $this->supply();
        $this->get(route('supplies.show', $supply))->assertOk();

        $this->post(route('supplies.movements.store', $supply), ['type' => 'in', 'quantity' => '5'])->assertSessionHas('success');
        $this->assertSame(15.0, (float) $supply->fresh()->stock);

        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '100'])->assertSessionHas('error');
        $this->assertSame(15.0, (float) $supply->fresh()->stock);

        $this->post(route('supplies.movements.store', $supply), ['type' => 'adjust', 'quantity' => '12'])->assertSessionHas('success');
        $this->assertSame(12.0, (float) $supply->fresh()->stock);
    }

    public function test_low_stock_alert_created_once_and_resolved(): void
    {
        $this->actingAsRole('admin');
        $supply = $this->supply(10, 5);

        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '6']);
        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '1']);
        $this->assertSame(1, Alert::query()->where('type', 'stock_low')->open()->count());

        $this->post(route('supplies.movements.store', $supply), ['type' => 'in', 'quantity' => '20']);
        $this->assertSame(0, Alert::query()->where('type', 'stock_low')->open()->count());
    }

    public function test_sequential_outs_never_leave_negative_stock(): void
    {
        $this->actingAsRole('admin');
        $supply = $this->supply(5, 0);
        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '4']);
        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '4'])->assertSessionHas('error');
        $this->assertSame(1.0, (float) $supply->fresh()->stock);
    }

    public function test_machines_and_maintenance(): void
    {
        app(ModuleService::class)->setEnabled('maintenance', true);
        $this->actingAsRole('admin');
        $this->get(route('machines.index'))->assertOk();
        $this->post(route('machines.store'), ['code' => 'CAL-1', 'name' => 'Calibradora', 'status' => 'operational'])->assertSessionHasNoErrors();
        $machine = Machine::query()->firstOrFail();
        $this->get(route('machines.show', $machine))->assertOk();

        $this->post(route('machines.maintenances.store', $machine), [
            'type' => 'preventive', 'date' => today()->toDateString(), 'next_maintenance_on' => today()->addMonth()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertSame(today()->addMonth()->toDateString(), $machine->fresh()->next_maintenance_on->toDateString());
    }

    public function test_maintenance_module_disabled_by_default(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('machines.index'))->assertNotFound();
        $this->get(route('cold-rooms.index'))->assertNotFound();
    }

    public function test_cold_room_out_of_range_reading_creates_single_alert(): void
    {
        app(ModuleService::class)->setEnabled('cold_rooms', true);
        $this->actingAsRole('admin');
        $this->post(route('cold-rooms.store'), ['code' => 'CAM-1', 'name' => 'Cámara 1', 'temp_min' => '2', 'temp_max' => '6', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $room = ColdRoom::query()->firstOrFail();

        $this->post(route('cold-rooms.readings.store', $room), ['temperature' => '4'])->assertSessionHas('success');
        $this->assertSame(0, Alert::query()->where('type', 'temperature')->count());

        $this->post(route('cold-rooms.readings.store', $room), ['temperature' => '9,5']);
        $this->post(route('cold-rooms.readings.store', $room), ['temperature' => '10']);
        $this->assertSame(1, Alert::query()->where('type', 'temperature')->open()->count());

        $this->get(route('cold-rooms.index'))->assertOk()->assertSee('FUERA DE RANGO');
        $this->get(route('cold-rooms.show', $room))->assertOk();
    }
}
