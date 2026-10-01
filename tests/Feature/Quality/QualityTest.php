<?php

namespace Tests\Feature\Quality;

use App\Enums\CrateStatus;
use App\Models\Crate;
use App\Models\Reason;
use App\Models\Reject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class QualityTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function reason(): Reason
    {
        return Reason::query()->where('type', 'reject')->firstOrFail();
    }

    public function test_pages_render(): void
    {
        $this->actingAsRole('quality');
        $this->get(route('quality.index'))->assertOk();
        $this->get(route('quality.create'))->assertOk();
        $this->get(route('rejects.index'))->assertOk()->assertSee('% merma');
        $this->get(route('rejects.create'))->assertOk();
    }

    public function test_lookup_by_code(): void
    {
        $this->actingAsRole('quality');
        $this->crate('CJ-1', CrateStatus::Processed);
        $this->getJson(route('quality.lookup', ['code' => 'CJ-1']))->assertOk()->assertJsonPath('type', 'crate')->assertJsonPath('eligible', true);
        $this->getJson(route('quality.lookup', ['code' => 'LOT-1']))->assertOk()->assertJsonPath('type', 'lot');
        $this->getJson(route('quality.lookup', ['code' => 'NADA']))->assertNotFound();
    }

    public function test_approve_crate_changes_state_and_history(): void
    {
        $this->actingAsRole('quality');
        $crate = $this->crate('CJ-1', CrateStatus::Processed);

        $this->postJson(route('quality.store'), ['target_type' => 'crate', 'target_id' => $crate->id, 'result' => 'approved'])->assertOk();

        $crate->refresh();
        $this->assertSame(CrateStatus::Approved, $crate->status);
        $this->assertSame('approved', $crate->quality_status);
        $this->assertDatabaseHas('state_histories', ['stateful_id' => $crate->id, 'to_state' => 'approved']);
        $this->get(route('quality.show', \App\Models\QualityControl::query()->first()))->assertOk();
    }

    public function test_reject_crate_registers_waste_and_percentage(): void
    {
        $this->actingAsRole('intake_operator');
        foreach (['CJ-1', 'CJ-2', 'CJ-3', 'CJ-4'] as $code) {
            $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => $code, 'weight' => '20']))->assertCreated();
        }
        $crate = Crate::query()->where('code', 'CJ-1')->firstOrFail();

        $this->actingAsRole('quality');
        $this->postJson(route('quality.store'), [
            'target_type' => 'crate', 'target_id' => $crate->id, 'result' => 'rejected',
            'register_reject' => true, 'reason_id' => $this->reason()->id,
        ])->assertOk();

        $this->assertSame(CrateStatus::Rejected, $crate->fresh()->status);
        $this->assertSame(20.0, (float) Reject::query()->sum('weight'));

        $summary = app(\App\Services\WasteService::class)->summary(now()->startOfDay(), now()->endOfDay());
        $this->assertSame(80.0, $summary['processed_kg']);
        $this->assertSame(25.0, $summary['waste_pct']);
        $this->get(route('rejects.index'))->assertOk()->assertSee('25,0 %');
    }

    public function test_invalid_transition_rejected_for_loaded_crate(): void
    {
        $this->actingAsRole('quality');
        $crate = $this->crate('CJ-1', CrateStatus::Loaded);
        $this->postJson(route('quality.store'), ['target_type' => 'crate', 'target_id' => $crate->id, 'result' => 'approved'])
            ->assertStatus(422);
        $this->assertSame(CrateStatus::Loaded, $crate->fresh()->status);
    }

    public function test_bulk_apply_to_lot(): void
    {
        $this->actingAsRole('quality');
        $this->crate('CJ-1', CrateStatus::Processed);
        $this->crate('CJ-2', CrateStatus::Processed);
        $this->crate('CJ-3', CrateStatus::Loaded);

        $response = $this->postJson(route('quality.store'), [
            'target_type' => 'lot', 'target_id' => $this->lot()->id, 'result' => 'approved', 'apply_to_crates' => true,
        ])->assertOk();

        $this->assertSame(2, $response->json('applied'));
        $this->assertSame(1, $response->json('skipped'));
        $this->assertSame(2, Crate::query()->where('status', 'approved')->count());
    }

    public function test_manual_reject_and_permissions(): void
    {
        $this->actingAsRole('quality');
        $this->post(route('rejects.store'), ['reason_id' => $this->reason()->id, 'weight' => '12,5', 'variety_id' => $this->variety()->id])
            ->assertSessionHas('success');
        $this->assertSame(12.5, (float) Reject::query()->value('weight'));

        $this->actingAsRole('loads_operator');
        $this->get(route('quality.index'))->assertForbidden();

        $this->actingAsRole('supervisor');
        $this->get(route('quality.index'))->assertOk();
        $this->get(route('quality.create'))->assertForbidden();
    }
}
