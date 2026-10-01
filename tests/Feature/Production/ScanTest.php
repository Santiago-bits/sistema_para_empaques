<?php

namespace Tests\Feature\Production;

use App\Enums\CrateStatus;
use App\Exceptions\ConcurrencyException;
use App\Models\Crate;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\SettingsService;
use App\Services\StateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_scan_screen_and_kiosk_render(): void
    {
        $this->actingAsRole('intake_operator');
        $this->get(route('production.scan'))->assertOk()->assertSee('Registrar');
        $this->get(route('kiosk'))->assertOk()->assertSee('Puesto de escaneo');
    }

    public function test_successful_scan_creates_crate_record_and_history(): void
    {
        $user = $this->actingAsRole('intake_operator');

        $this->postJson(route('production.scan.store'), $this->scanPayload(['idempotency_key' => 'k1']))
            ->assertCreated()->assertJsonPath('ok', true)->assertJsonPath('record.crate', 'CJ-100')
            ->assertJsonPath('totals.crates', 1);

        $crate = Crate::query()->where('code', 'CJ-100')->firstOrFail();
        $this->assertSame(CrateStatus::Processed, $crate->status);
        $this->assertSame('18.50', $crate->weight);
        $this->assertSame($user->id, $crate->processed_by);
        $this->assertDatabaseHas('production_records', ['crate_id' => $crate->id, 'user_id' => $user->id, 'weight' => 18.5]);
        $this->assertDatabaseHas('state_histories', ['stateful_type' => 'crate', 'stateful_id' => $crate->id, 'to_state' => 'processed']);
    }

    public function test_same_code_scanned_twice_is_duplicate_and_only_one_record(): void
    {
        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload())->assertCreated();

        $this->postJson(route('production.scan.store'), $this->scanPayload())
            ->assertStatus(409)->assertJsonPath('reason', 'duplicate');

        $this->assertSame(1, ProductionRecord::query()->count());
    }

    public function test_retry_with_same_idempotency_key_returns_same_result(): void
    {
        $this->actingAsRole('intake_operator');
        $first = $this->postJson(route('production.scan.store'), $this->scanPayload(['idempotency_key' => 'abc']))->assertCreated();
        $second = $this->postJson(route('production.scan.store'), $this->scanPayload(['idempotency_key' => 'abc']))->assertOk();

        $this->assertTrue($second->json('replay'));
        $this->assertSame($first->json('record.id'), $second->json('record.id'));
        $this->assertSame(1, ProductionRecord::query()->count());
    }

    public function test_idempotency_key_of_another_user_is_rejected(): void
    {
        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['idempotency_key' => 'shared']))->assertCreated();

        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => 'CJ-200', 'idempotency_key' => 'shared']))
            ->assertStatus(422);
        $this->assertSame(1, ProductionRecord::query()->count());
    }

    public function test_stale_version_cannot_overwrite_concurrent_change(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-9');
        $stale = Crate::query()->find($crate->id);

        app(StateTransitionService::class)->transition($crate, CrateStatus::Processed);

        $this->expectException(ConcurrencyException::class);
        app(StateTransitionService::class)->transition($stale, CrateStatus::Voided, force: true);
    }

    public function test_out_of_range_weight_requires_supervisor(): void
    {
        $this->actingAsRole('intake_operator');
        $supervisor = User::factory()->role('supervisor')->create(['username' => 'jefe']);

        $this->postJson(route('production.scan.store'), $this->scanPayload(['weight' => '45']))
            ->assertStatus(422)->assertJsonPath('requires_authorization', true);
        $this->assertSame(0, Crate::query()->count(), 'El alta automática se revierte');

        $this->postJson(route('production.scan.store'), $this->scanPayload([
            'weight' => '45', 'supervisor_login' => 'jefe', 'supervisor_password' => 'mala', 'authorization_reason' => 'Cajón grande',
        ]))->assertStatus(422)->assertJsonPath('field', 'supervisor');

        $this->postJson(route('production.scan.store'), $this->scanPayload([
            'weight' => '45', 'supervisor_login' => 'jefe', 'supervisor_password' => 'password', 'authorization_reason' => 'Cajón grande',
        ]))->assertCreated();

        $record = ProductionRecord::query()->firstOrFail();
        $this->assertSame($supervisor->id, $record->authorized_by);
        $this->assertSame('Cajón grande', $record->authorization_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'authorization_failed']);
        $this->assertDatabaseMissing('audit_logs', ['new_values' => '%password%']);
    }

    public function test_operator_without_authorize_permission_cannot_authorize(): void
    {
        $this->actingAsRole('intake_operator');
        User::factory()->role('intake_operator')->create(['username' => 'otro']);

        $this->postJson(route('production.scan.store'), $this->scanPayload([
            'weight' => '45', 'supervisor_login' => 'otro', 'supervisor_password' => 'password', 'authorization_reason' => 'x',
        ]))->assertStatus(422)->assertJsonPath('field', 'supervisor');
        $this->assertSame(0, ProductionRecord::query()->count());
    }

    public function test_unknown_or_inactive_packer_is_rejected(): void
    {
        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['packer_code' => 'EMB999']))
            ->assertStatus(422)->assertJsonPath('field', 'packer');

        $this->packer('EMB050', false);
        $this->postJson(route('production.scan.store'), $this->scanPayload(['packer_code' => 'emb050']))
            ->assertStatus(422)->assertJsonPath('reason', 'packer_inactive');
    }

    public function test_voided_crate_cannot_be_scanned(): void
    {
        $this->actingAsRole('intake_operator');
        $this->crate('CJ-100', CrateStatus::Voided);
        $this->postJson(route('production.scan.store'), $this->scanPayload())->assertStatus(422)->assertJsonPath('reason', 'crate_voided');
    }

    public function test_invalid_characters_in_code_rejected(): void
    {
        $this->actingAsRole('intake_operator');
        $this->postJson(route('production.scan.store'), $this->scanPayload(['crate_code' => "CJ'1<script>"]))
            ->assertStatus(422)->assertJsonValidationErrors('crate_code');
    }

    public function test_auto_create_disabled_requires_existing_crate(): void
    {
        $this->actingAsRole('intake_operator');
        app(SettingsService::class)->set('production.auto_create_crate', false);
        $this->postJson(route('production.scan.store'), $this->scanPayload())->assertStatus(422)->assertJsonPath('reason', 'crate_not_found');

        $this->crate('CJ-100');
        $this->postJson(route('production.scan.store'), $this->scanPayload())->assertCreated();
    }

    public function test_lookup_reports_duplicate_and_packer(): void
    {
        $this->actingAsRole('intake_operator');
        $this->crate('CJ-5', CrateStatus::Processed);
        $this->packer();

        $this->getJson(route('production.scan.lookup', ['crate' => 'CJ-5', 'packer' => 'EMB001']))
            ->assertOk()->assertJsonPath('crate.reason', 'duplicate')->assertJsonPath('packer.ok', true);
        $this->getJson(route('production.scan.lookup', ['crate' => 'NUEVO-1']))->assertJsonPath('crate.reason', 'new');
    }

    public function test_permissions_module_and_lan_restrictions(): void
    {
        $this->actingAsRole('loads_operator');
        $this->get(route('production.scan'))->assertForbidden();
        $this->postJson(route('production.scan.store'), $this->scanPayload())->assertForbidden();

        $this->actingAsRole('intake_operator');
        app(SettingsService::class)->set('network.lan_only_modules', ['production']);
        app(SettingsService::class)->set('network.allowed_ranges', ['10.1.1.0/24']);
        $this->get(route('production.scan'))->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.20'])->get(route('production.scan'))->assertOk();

        app(ModuleService::class)->setEnabled('production', false);
        $this->get(route('production.scan'))->assertNotFound();
    }

    public function test_kiosk_user_is_confined_to_scan_screen(): void
    {
        $this->actingAsRole('intake_operator', ['kiosk_mode' => true]);
        $this->get(route('pallets.index'))->assertRedirect(route('kiosk'));
        $this->get(route('home'))->assertRedirect(route('kiosk'));
        $this->get(route('kiosk'))->assertOk();
    }
}
