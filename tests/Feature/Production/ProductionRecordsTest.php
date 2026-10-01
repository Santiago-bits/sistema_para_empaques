<?php

namespace Tests\Feature\Production;

use App\Enums\CrateStatus;
use App\Models\Crate;
use App\Models\ProductionRecord;
use App\Models\ProductionStoppage;
use App\Models\Reason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class ProductionRecordsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function scanAs(string $role, string $code = 'CJ-100'): void
    {
        $this->actingAsRole($role);
        $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => $code]))->assertCreated();
    }

    public function test_records_list_and_void_allows_rescan(): void
    {
        $this->scanAs('intake_operator');
        $this->actingAsRole('supervisor');
        $record = ProductionRecord::query()->firstOrFail();

        $this->get(route('production.index'))->assertOk()->assertSee('CJ-100');

        $this->post(route('production.void', $record), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('production.void', $record), ['reason' => 'Embalador equivocado'])->assertSessionHas('success');

        $this->assertNotNull($record->fresh()->voided_at);
        $crate = Crate::query()->where('code', 'CJ-100')->firstOrFail();
        $this->assertSame(CrateStatus::Registered, $crate->status);
        $this->assertNull($crate->packer_id);

        $this->scanAs('intake_operator');
        $this->assertSame(1, ProductionRecord::query()->valid()->count());
        $this->assertSame(2, ProductionRecord::query()->count());
    }

    public function test_intake_operator_cannot_void(): void
    {
        $this->scanAs('intake_operator');
        $this->post(route('production.void', ProductionRecord::query()->first()), ['reason' => 'prueba larga'])->assertForbidden();
    }

    public function test_packer_sees_only_own_production(): void
    {
        $this->scanAs('intake_operator', 'CJ-1');
        $other = $this->packer('EMB002');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => 'CJ-2', 'packer_code' => 'EMB002']))->assertCreated();

        $this->actingAsRole('packer', ['packer_id' => $other->id]);
        $this->get(route('production.mine'))->assertOk()->assertSee('CJ-2')->assertDontSee('CJ-1');
        $this->get(route('production.index'))->assertForbidden();
        $this->get(route('home'))->assertRedirect(route('production.mine'));
    }

    public function test_packer_without_link_sees_message(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('production.mine'))->assertOk()->assertSee('no está vinculado');
    }

    public function test_stoppage_start_finish_and_single_open_per_line(): void
    {
        $this->actingAsRole('intake_operator');
        $reason = Reason::query()->where('type', 'stoppage')->firstOrFail();

        $this->get(route('stoppages.index'))->assertOk();
        $this->post(route('stoppages.store'), ['reason_id' => $reason->id])->assertSessionHas('success');
        $this->post(route('stoppages.store'), ['reason_id' => $reason->id])->assertSessionHas('error');

        $stoppage = ProductionStoppage::query()->firstOrFail();
        $this->travel(25)->minutes();
        $this->put(route('stoppages.update', $stoppage))->assertSessionHas('success');
        $this->assertSame(25, $stoppage->fresh()->duration_minutes);

        $this->put(route('stoppages.update', $stoppage))->assertSessionHas('error');
    }
}
