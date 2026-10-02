<?php

namespace Tests\Feature\Reports;

use App\Models\DailyClosing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class ClosingTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_preview_and_close_day_with_frozen_snapshot(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('closings.index'))->assertOk()->assertSee('Cerrar el día de hoy');
        $this->get(route('closings.create'))->assertOk()->assertSee('Confirmar cierre');

        $this->post(route('closings.store'), ['date' => today()->toDateString(), 'notes' => 'Jornada normal'])
            ->assertRedirect();

        $closing = DailyClosing::query()->sole();
        $this->assertSame(today()->toDateString(), $closing->snapshot['date']);
        $this->assertArrayHasKey('kg_processed', $closing->snapshot);
        $this->assertDatabaseHas('audit_logs', ['action' => 'daily_closing', 'auditable_id' => $closing->id]);
        $this->get(route('closings.show', $closing))->assertOk()->assertSee('Jornada normal');
        $this->get(route('closings.create'))->assertSee('ya está cerrado');
    }

    public function test_same_day_cannot_be_closed_twice_and_future_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('closings.store'), ['date' => today()->toDateString()]);
        $this->post(route('closings.store'), ['date' => today()->toDateString()])->assertSessionHas('error');
        $this->post(route('closings.store'), ['date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');
        $this->assertSame(1, DailyClosing::query()->count());
    }

    public function test_reopen_requires_permission_and_reason_then_allows_closing_again(): void
    {
        $this->actingAsRole('supervisor');
        $this->post(route('closings.store'), ['date' => today()->toDateString()]);
        $closing = DailyClosing::query()->sole();

        // El supervisor cierra pero no puede reabrir (closings.reopen no está en su rol).
        $this->post(route('closings.reopen', $closing), ['reason' => 'Faltó cargar un pallet'])->assertForbidden();

        $this->actingAsRole('admin');
        $this->post(route('closings.reopen', $closing), ['reason' => 'no'])->assertSessionHasErrors('reason');
        $this->post(route('closings.reopen', $closing), ['reason' => 'Faltó cargar un pallet'])->assertRedirect();
        $this->assertNotNull($closing->fresh()->reopened_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reopen', 'auditable_id' => $closing->id, 'reason' => 'Faltó cargar un pallet']);

        $this->post(route('closings.store'), ['date' => today()->toDateString()])->assertRedirect(route('closings.show', $closing));
        $this->assertNull($closing->fresh()->reopened_at);
        $this->assertSame(1, DailyClosing::query()->count());
    }

    public function test_closings_require_permission(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('closings.index'))->assertForbidden();
        $this->post(route('closings.store'), ['date' => today()->toDateString()])->assertForbidden();
    }
}
