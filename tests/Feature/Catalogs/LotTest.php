<?php

namespace Tests\Feature\Catalogs;

use App\Models\Lot;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotTest extends TestCase
{
    use RefreshDatabase;

    private function producer(): Producer
    {
        return Producer::query()->create(['code' => 'P1', 'name' => 'Finca Uno']);
    }

    public function test_lot_gets_automatic_code_and_history(): void
    {
        $this->actingAsRole('admin');
        $producer = $this->producer();

        $this->get(route('lots.index'))->assertOk();
        $this->get(route('lots.create'))->assertOk()->assertSee('LOT-00001');

        $this->post(route('lots.store'), ['date' => today()->toDateString(), 'producer_id' => $producer->id])
            ->assertSessionHasNoErrors()->assertRedirect();

        $lot = Lot::query()->firstOrFail();
        $this->assertSame('LOT-00001', $lot->code);
        $this->assertSame('open', $lot->status);
        $this->assertDatabaseHas('state_histories', ['stateful_type' => 'lot', 'stateful_id' => $lot->id, 'to_state' => 'open']);
        $this->get(route('lots.show', $lot))->assertOk()->assertSee('LOT-00001');
        $this->get(route('lots.edit', $lot))->assertOk();
    }

    public function test_duplicate_lot_code_rejected(): void
    {
        $this->actingAsRole('admin');
        $producer = $this->producer();
        $this->post(route('lots.store'), ['code' => 'L-1', 'date' => today()->toDateString(), 'producer_id' => $producer->id]);
        $this->post(route('lots.store'), ['code' => 'l-1', 'date' => today()->toDateString(), 'producer_id' => $producer->id])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, Lot::query()->count());
    }

    public function test_close_and_void_with_reason(): void
    {
        $this->actingAsRole('admin');
        $lot = Lot::query()->create(['code' => 'L1', 'date' => today(), 'producer_id' => $this->producer()->id, 'status' => 'open']);

        $this->post(route('lots.close', $lot))->assertSessionHas('success');
        $this->assertSame('closed', $lot->fresh()->status);

        // Cerrar dos veces no aplica nada.
        $this->post(route('lots.close', $lot))->assertSessionHas('error');

        $this->post(route('lots.void', $lot), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('lots.void', $lot), ['reason' => 'Cargado por error'])->assertSessionHas('success');
        $this->assertSame('voided', $lot->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'void', 'auditable_type' => 'lot', 'reason' => 'Cargado por error']);

        $this->get(route('lots.edit', $lot))->assertForbidden();
    }

    public function test_future_date_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('lots.store'), ['date' => today()->addDay()->toDateString(), 'producer_id' => $this->producer()->id])
            ->assertSessionHasErrors('date');
    }

    public function test_quality_role_cannot_create_lot(): void
    {
        $this->actingAsRole('quality');
        $this->get(route('lots.index'))->assertOk();
        $this->get(route('lots.create'))->assertForbidden();
    }
}
