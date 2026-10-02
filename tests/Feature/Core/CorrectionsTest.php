<?php

namespace Tests\Feature\Core;

use App\Enums\CrateStatus;
use App\Enums\LoadStatus;
use App\Models\AccountMovement;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Check;
use App\Models\Client;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\QualityControl;
use App\Models\Reason;
use App\Models\Reject;
use App\Models\Transporter;
use App\Models\Truck;
use App\Services\AccountService;
use App\Services\CashService;
use App\Services\LoadService;
use App\Services\QualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** «Si te equivocás, se corrige sin borrar ni rehacer el proceso»: cada corrección exige motivo y queda auditada. */
class CorrectionsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_quality_control_and_reject_are_correctable(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-QC', CrateStatus::Processed);
        $result = app(QualityService::class)->record(['target_type' => 'crate', 'target_id' => $crate->id, 'result' => 'approved']);
        $control = $result['control'];

        $this->get(route('quality.edit', $control))->assertOk();
        $this->put(route('quality.update', $control), ['result' => 'rejected', 'controlled_at' => now()->format('Y-m-d\TH:i'), 'rot_pct' => '12,5'])
            ->assertSessionHasErrors('reason');
        $this->put(route('quality.update', $control), ['result' => 'rejected', 'controlled_at' => now()->format('Y-m-d\TH:i'), 'rot_pct' => '12,5', 'reason' => 'Se marcó aprobado por error'])
            ->assertRedirect(route('quality.show', $control));
        $this->assertSame('rejected', $control->fresh()->result);
        $this->assertSame('12.50', $control->fresh()->rot_pct);
        $this->assertSame('rejected', $crate->fresh()->quality_status);
        $this->assertTrue(AuditLog::query()->where('auditable_type', 'quality_control')->where('reason', 'Se marcó aprobado por error')->exists());

        $reason = Reason::query()->where('type', 'reject')->firstOrFail();
        $reject = Reject::query()->create(['reason_id' => $reason->id, 'weight' => 10, 'user_id' => auth()->id(), 'rejected_at' => now()]);
        $this->put(route('rejects.update', $reject), ['reason_id' => $reason->id, 'weight' => '8,5', 'rejected_at' => now()->format('Y-m-d\TH:i'), 'reason' => 'Peso mal tipeado'])
            ->assertRedirect(route('rejects.index'));
        $this->assertSame('8.50', $reject->fresh()->weight);
    }

    public function test_crate_weight_correction_in_a_draft_load_updates_totals(): void
    {
        $user = $this->actingAsRole('admin');
        $crate = $this->crate('CJ-W', CrateStatus::Approved, ['weight' => 20]);
        $load = app(LoadService::class)->create(['date' => today()->toDateString()], $user);
        app(LoadService::class)->assignCrates($load, [$crate->id], $user);
        $this->assertSame('20.00', $load->fresh()->total_kg);

        $this->put(route('crates.update', $crate), ['weight' => '18.5', 'version' => $crate->fresh()->version, 'reason' => 'Balanza descalibrada'])->assertSessionHasNoErrors()->assertSessionMissing('error')
            ->assertRedirect();
        $this->assertSame('18.50', $crate->fresh()->weight);
        $this->assertSame('18.50', $load->fresh()->total_kg);
    }

    public function test_closed_load_details_and_freight_can_be_corrected(): void
    {
        $user = $this->actingAsRole('admin');
        $t1 = Transporter::query()->create(['business_name' => 'Fletes Uno']);
        $t2 = Transporter::query()->create(['business_name' => 'Fletes Dos']);
        $load = Load::query()->create(['number' => 'CAR-9', 'date' => today(), 'status' => LoadStatus::Dispatched, 'transporter_id' => $t1->id,
            'freight_amount' => 100000, 'dispatched_at' => now()]);
        app(AccountService::class)->postFreight($load);

        $this->get(route('loads.correct', $load))->assertOk();
        $this->put(route('loads.correct.save', $load), ['date' => today()->toDateString(), 'transporter_id' => $t2->id, 'freight_amount' => '120.000', 'guide_number' => 'DTV-77'])
            ->assertSessionHasErrors('reason');
        $this->put(route('loads.correct.save', $load), ['date' => today()->toDateString(), 'transporter_id' => $t2->id, 'freight_amount' => '120.000',
            'guide_number' => 'DTV-77', 'reason' => 'Viajó otro transportista'])->assertRedirect(route('loads.show', $load));

        $this->assertSame('DTV-77', $load->fresh()->guide_number);
        $this->assertSame(0.0, app(AccountService::class)->balance($t1));
        $this->assertSame(-120000.0, app(AccountService::class)->balance($t2));
    }

    public function test_settled_lot_is_resettled_when_corrected(): void
    {
        $user = $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P9', 'name' => 'Finca Norte']);
        $lot = Lot::query()->create(['code' => 'LOT-R', 'date' => today(), 'producer_id' => $producer->id, 'status' => 'open', 'kg_received' => 1000, 'price_per_kg' => 100]);
        app(AccountService::class)->settleLot($lot, $user);

        $payload = ['date' => today()->toDateString(), 'producer_id' => $producer->id, 'kg_received' => '1.200', 'price_per_kg' => '100'];
        $this->put(route('lots.update', $lot), $payload)->assertSessionHas('error');
        $this->put(route('lots.update', $lot), $payload + ['reason' => 'Faltaba un bin en la pesada'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(-120000.0, app(AccountService::class)->balance($producer));
        $this->assertNotNull($lot->fresh()->settled_at);
        $this->assertSame(1, AccountMovement::query()->whereNotNull('voided_at')->where('type', 'purchase')->count());
    }

    public function test_account_cash_and_check_corrections(): void
    {
        $user = $this->actingAsRole('admin');
        $client = Client::query()->create(['business_name' => 'Cliente Corrección', 'cuit' => '30712345671']);
        app(CashService::class)->open(0, null, $user);
        $collection = app(AccountService::class)->registerPayment($client, ['direction' => 'collection', 'amount' => 5000, 'method' => 'cash', 'date' => today()->toDateString()], $user);

        // Sólo el detalle: se corrige en el lugar.
        $this->put(route('accounts.movements.correct', $collection), ['amount' => '5.000', 'date' => today()->toDateString(), 'description' => 'Cobro factura 12', 'reason' => 'Faltaba el detalle'])
            ->assertSessionHas('success');
        $this->assertSame('Cobro factura 12', $collection->fresh()->description);

        // Cambia el importe: se re-asienta y la caja también se corrige.
        $this->put(route('accounts.movements.correct', $collection), ['amount' => '4.500', 'date' => today()->toDateString(), 'description' => 'Cobro factura 12', 'reason' => 'Se cobró menos'])
            ->assertSessionHas('success');
        $this->assertSame(-4500.0, app(AccountService::class)->balance($client));
        $this->assertSame(4500.0, app(CashService::class)->current()->totals()['balance']);

        // Movimiento manual de caja.
        $cash = app(CashService::class)->addMovement(['direction' => 'out', 'category' => 'expenses', 'description' => 'Nafta', 'amount' => 1000], $user);
        $this->put(route('cash.movements.correct', $cash), ['category' => 'expenses', 'description' => 'Nafta autoelevador', 'amount' => '800', 'reason' => 'Ticket mal leído'])
            ->assertSessionHas('success');
        $this->assertSame(3700.0, app(CashService::class)->current()->totals()['balance']);
        // Un movimiento que vino de una cuenta corriente se corrige desde la cuenta.
        $linked = CashMovement::query()->whereNotNull('account_movement_id')->whereNull('voided_at')->firstOrFail();
        $this->put(route('cash.movements.correct', $linked), ['category' => 'collection', 'description' => 'x', 'amount' => '1', 'reason' => 'probando'])->assertSessionHas('error');

        // Cheque: corregir número y monto; el cobro asociado se re-asienta.
        $byCheck = app(AccountService::class)->registerPayment($client, ['direction' => 'collection', 'amount' => 10000, 'method' => 'check', 'date' => today()->toDateString(),
            'check' => ['bank' => 'Nación', 'number' => '111', 'payment_date' => today()->addDays(5)->toDateString()]], $user);
        $check = Check::query()->findOrFail($byCheck->source_id);
        $this->put(route('checks.update', $check), ['bank' => 'Nación', 'number' => '1111', 'amount' => '12.000', 'issued_on' => today()->toDateString(),
            'payment_date' => today()->addDays(5)->toDateString(), 'reason' => 'Número y monto mal cargados'])->assertRedirect(route('checks.show', $check));
        $this->assertSame('1111', $check->fresh()->number);
        $this->assertSame(-16500.0, app(AccountService::class)->balance($client));

        // Los generados por otro proceso se corrigen en su origen.
        $active = AccountMovement::query()->where('source_type', 'check')->whereNull('voided_at')->firstOrFail();
        $this->put(route('accounts.movements.correct', $active), ['amount' => '1', 'date' => today()->toDateString(), 'description' => 'x', 'reason' => 'probando'])
            ->assertSessionHas('error');
    }
}
