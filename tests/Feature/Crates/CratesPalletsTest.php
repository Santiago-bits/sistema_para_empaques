<?php

namespace Tests\Feature\Crates;

use App\Enums\CrateStatus;
use App\Enums\PalletStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\StateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class CratesPalletsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_pallet_crud_and_void(): void
    {
        $this->actingAsRole('admin');
        $lot = $this->lot();

        $this->get(route('pallets.index'))->assertOk();
        $this->get(route('pallets.create'))->assertOk()->assertSee('PAL-000001');
        $this->post(route('pallets.store'), ['received_at' => now()->format('Y-m-d\TH:i'), 'lot_id' => $lot->id, 'quantity' => 40])
            ->assertSessionHasNoErrors();

        $pallet = Pallet::query()->firstOrFail();
        $this->assertSame('PAL-000001', $pallet->code);
        $this->assertSame($lot->producer_id, $pallet->producer_id, 'Hereda productor del lote');
        $this->get(route('pallets.show', $pallet))->assertOk();
        $this->get(route('pallets.edit', $pallet))->assertOk();

        $this->post(route('pallets.void', $pallet), ['reason' => 'Error de carga'])->assertSessionHas('success');
        $this->assertSame(PalletStatus::Voided, $pallet->fresh()->status);
    }

    public function test_crate_pages_filters_and_traceability(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-1', CrateStatus::Processed, ['pallet_id' => $this->pallet()->id]);

        $this->get(route('crates.index', ['code' => 'CJ', 'status' => 'processed']))->assertOk()->assertSee('CJ-1');
        $this->get(route('crates.show', $crate))->assertOk()->assertSee('Cadena de trazabilidad')->assertSee('PAL-1');
        $this->get(route('traceability.index', ['code' => 'CJ-1']))->assertOk()->assertSee('Finca PROD-1');
        $this->get(route('traceability.index', ['code' => 'PAL-1']))->assertOk()->assertSee('Pallet');
        $this->get(route('traceability.index', ['code' => 'NOEXISTE']))->assertOk()->assertSee('No se encontró');
    }

    public function test_editing_critical_field_requires_reason_and_audits(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-1', CrateStatus::Processed);
        $base = ['version' => $crate->version, 'variety_id' => $crate->variety_id, 'size_id' => $crate->size_id, 'packer_id' => $crate->packer_id];

        $this->put(route('crates.update', $crate), $base + ['weight' => '19,20'])->assertSessionHas('error');
        $this->assertSame('18.50', $crate->fresh()->weight);

        $this->put(route('crates.update', $crate), $base + ['weight' => '19,20', 'reason' => 'Corrección de balanza'])->assertSessionHas('success');
        $this->assertSame('19.20', $crate->fresh()->weight);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'crate', 'action' => 'update', 'reason' => 'Corrección de balanza']);
    }

    public function test_edit_with_stale_version_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-1', CrateStatus::Processed);
        $this->put(route('crates.update', $crate), ['version' => $crate->version + 5, 'weight' => '18.5', 'notes' => 'x'])
            ->assertSessionHas('error', 'Otro usuario modificó este registro al mismo tiempo. Actualizá la pantalla e intentá nuevamente.');
        $this->assertNull($crate->fresh()->notes);
    }

    public function test_invalid_transition_voided_to_loaded_is_rejected(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-1', CrateStatus::Voided);
        $this->expectException(InvalidTransitionException::class);
        app(StateTransitionService::class)->transition($crate, CrateStatus::Loaded);
    }

    public function test_void_crate_with_reason_voids_production(): void
    {
        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => 'CJ-7']))->assertCreated();
        $crate = Crate::query()->where('code', 'CJ-7')->firstOrFail();

        $this->actingAsRole('supervisor');
        $this->post(route('crates.void', $crate), ['reason' => 'Cajón roto'])->assertSessionHas('success');
        $this->assertSame(CrateStatus::Voided, $crate->fresh()->status);
        $this->assertSame(0, \App\Models\ProductionRecord::query()->valid()->count());
    }

    public function test_labels_render_barcode_and_qr(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-1', CrateStatus::Processed);
        $this->get(route('labels.crates'))->assertOk();
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()->assertSee('CJ-1')->assertSee('<svg', false);
        $this->get(route('labels.pallets', ['ids' => [$this->pallet()->id]]))->assertOk()->assertSee('PAL-1');

        $this->post(route('labels.generate'), ['quantity' => 3])->assertRedirect();
        $this->assertSame(4, Crate::query()->count());
    }

    public function test_manual_crate_with_production(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('crates.store'), [
            'process' => '1', 'packer_id' => $this->packer()->id, 'variety_id' => $this->variety()->id,
            'size_id' => $this->makeSize()->id, 'weight' => '18', 'lot_id' => $this->lot()->id,
        ])->assertSessionHasNoErrors();
        $crate = Crate::query()->firstOrFail();
        $this->assertSame(CrateStatus::Processed, $crate->status);
    }

    public function test_quality_role_cannot_create_pallets_or_crates(): void
    {
        $this->actingAsRole('quality');
        $this->get(route('pallets.index'))->assertOk();
        $this->get(route('pallets.create'))->assertForbidden();
        $this->get(route('crates.create'))->assertForbidden();
    }
}
