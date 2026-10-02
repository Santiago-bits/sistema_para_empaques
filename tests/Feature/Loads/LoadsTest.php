<?php

namespace Tests\Feature\Loads;

use App\Enums\CrateStatus;
use App\Enums\LoadStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ConcurrencyException;
use App\Models\Client;
use App\Models\Crate;
use App\Models\Destination;
use App\Models\DispatchCheck;
use App\Models\Driver;
use App\Models\Load;
use App\Models\LoadCrate;
use App\Models\Remito;
use App\Models\Truck;
use App\Services\LoadService;
use App\Services\ModuleService;
use App\Services\RemitoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class LoadsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function newLoad(): Load
    {
        $client = Client::query()->firstOrCreate(['business_name' => 'Cliente SA'], ['cuit' => '30712345671']);
        $destination = Destination::query()->firstOrCreate(['name' => 'Rosario'], ['client_id' => $client->id, 'locality' => 'Rosario', 'province' => 'Santa Fe']);
        $truck = Truck::query()->firstOrCreate(['plate' => 'AB123CD']);
        $driver = Driver::query()->firstOrCreate(['dni' => '25111222'], ['first_name' => 'Carlos', 'last_name' => 'Díaz']);

        return app(LoadService::class)->create([
            'client_id' => $client->id, 'destination_id' => $destination->id, 'truck_id' => $truck->id, 'driver_id' => $driver->id,
        ], auth()->user());
    }

    private function crates(int $n, CrateStatus $status = CrateStatus::Approved): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = $this->crate('CJ-'.uniqid(), $status)->id;
        }

        return $ids;
    }

    public function test_pages_render(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $this->get(route('loads.index'))->assertOk();
        $this->get(route('loads.create'))->assertOk();
        $this->get(route('loads.show', $load))->assertOk();
        $this->get(route('loads.builder', $load))->assertOk();
        $this->getJson(route('loads.available', $load))->assertOk();
        $this->getJson(route('loads.content', $load))->assertOk()->assertJsonPath('status', 'draft');
        $this->get(route('loads.close.show', $load))->assertOk();
    }

    public function test_create_load_via_form(): void
    {
        $this->actingAsRole('loads_operator');
        $this->post(route('loads.store'), ['date' => today()->toDateString(), 'planned_crates' => 100])->assertRedirect();
        $load = Load::query()->firstOrFail();
        $this->assertSame('CARG-00001', $load->number);
        $this->assertSame(LoadStatus::Draft, $load->status);
    }

    public function test_assign_crates_by_ids_codes_and_take(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $ids = $this->crates(5);

        $this->postJson(route('loads.crates.assign', $load), ['ids' => [$ids[0], $ids[1]]])->assertOk()->assertJsonPath('assigned', 2);
        $code = Crate::query()->find($ids[2])->code;
        $this->postJson(route('loads.crates.assign', $load), ['codes' => [$code, 'NOEXISTE']])->assertOk()
            ->assertJsonPath('assigned', 1)->assertJsonPath('rejected.0.reason', 'Código inexistente.');
        $this->postJson(route('loads.crates.assign', $load), ['take' => 10])->assertOk()->assertJsonPath('assigned', 2);

        $load->refresh();
        $this->assertSame(5, $load->total_crates);
        $this->assertSame(92.5, (float) $load->total_kg);
        $this->assertSame(5, Crate::query()->where('status', 'reserved')->count());
    }

    public function test_crate_from_another_warehouse_is_rejected(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $ids = $this->crates(1);
        $other = \App\Models\Warehouse::query()->create(['company_id' => \App\Models\Company::query()->value('id'), 'name' => 'Galpón B', 'code' => 'B']);
        Crate::query()->whereKey($ids[0])->update(['warehouse_id' => $other->id]);

        $this->postJson(route('loads.crates.assign', $load), ['ids' => $ids])->assertOk()
            ->assertJsonPath('assigned', 0)->assertJsonPath('rejected.0.reason', 'Pertenece a otro galpón.');
    }

    public function test_tampered_filters_do_not_break_the_builder(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $this->crates(2);

        $this->getJson(route('loads.available', $load).'?date_from[]=x&variety_id=1 OR 1=1&weight_min=abc&q=%25')->assertOk();
        $this->postJson(route('loads.crates.assign', $load), ['take' => 5, 'filters' => ['date_to' => ['x'], 'q' => '%']])->assertOk();
    }

    public function test_two_loads_competing_for_same_crate_only_one_wins(): void
    {
        $this->actingAsRole('loads_operator');
        $a = $this->newLoad();
        $b = $this->newLoad();
        $ids = $this->crates(3);

        $first = app(LoadService::class)->assignCrates($a, $ids, auth()->user());
        $second = app(LoadService::class)->assignCrates($b, $ids, auth()->user());

        $this->assertSame(3, $first['assigned']);
        $this->assertSame(0, $second['assigned']);
        $this->assertCount(3, $second['rejected']);
        $this->assertStringContainsString($a->number, $second['rejected'][0]['reason']);
        $this->assertSame(3, LoadCrate::query()->whereNotNull('active_crate_id')->count());
        $this->assertSame(3, Crate::query()->where('current_load_id', $a->id)->count());
    }

    public function test_race_at_database_level_is_blocked_by_conditional_update(): void
    {
        $this->actingAsRole('loads_operator');
        $a = $this->newLoad();
        $b = $this->newLoad();
        [$id] = $this->crates(1);

        // Simula que otra PC asignó el cajón entre la lectura y la escritura.
        \Illuminate\Support\Facades\DB::table('crates')->where('id', $id)->update(['current_load_id' => $a->id, 'status' => 'reserved']);
        $result = app(LoadService::class)->assignCrates($b, [$id], auth()->user());

        $this->assertSame(0, $result['assigned']);
        $this->assertSame($a->id, Crate::query()->find($id)->current_load_id);
    }

    public function test_invalid_states_rejected(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $ids = array_merge($this->crates(1, CrateStatus::Registered), $this->crates(1, CrateStatus::Voided), $this->crates(1, CrateStatus::Rejected));
        $result = app(LoadService::class)->assignCrates($load, $ids, auth()->user());
        $this->assertSame(0, $result['assigned']);
        $this->assertCount(3, $result['rejected']);
    }

    public function test_remove_crates_returns_them_to_available(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $ids = $this->crates(2);
        app(LoadService::class)->assignCrates($load, $ids, auth()->user());

        $this->postJson(route('loads.crates.remove', $load), ['ids' => [$ids[0]]])->assertOk()->assertJsonPath('removed', 1);
        $this->assertSame(CrateStatus::Approved, Crate::query()->find($ids[0])->status);
        $this->assertNull(Crate::query()->find($ids[0])->current_load_id);
        $this->assertSame(1, $load->fresh()->total_crates);
    }

    public function test_close_requires_confirmation_and_cannot_close_twice(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        app(LoadService::class)->assignCrates($load, $this->crates(2), auth()->user());
        $load->refresh();

        $this->post(route('loads.close', $load), ['version' => $load->version])->assertSessionHasErrors('confirm');
        $this->post(route('loads.close', $load), ['version' => $load->version + 1, 'confirm' => '1'])->assertSessionHas('error');

        $stale = Load::query()->find($load->id);
        $this->post(route('loads.close', $load), ['version' => $load->version, 'confirm' => '1'])->assertSessionHas('success');
        $this->assertSame(LoadStatus::Closed, $load->fresh()->status);
        $this->assertSame(2, Crate::query()->where('status', 'loaded')->count());

        // Segundo cierre concurrente (instancia desactualizada): no aplica nada.
        try {
            app(LoadService::class)->close($stale, auth()->user());
            $this->fail('Debió rechazarse el segundo cierre');
        } catch (BusinessException $e) {
            $this->assertTrue(true);
        }

        // Cerrada no admite asignar.
        $this->postJson(route('loads.crates.assign', $load), ['ids' => $this->crates(1)])->assertStatus(422);
    }

    public function test_cannot_close_empty_load(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        $this->post(route('loads.close', $load), ['version' => $load->version, 'confirm' => '1'])->assertSessionHas('error');
    }

    public function test_reopen_requires_permission_and_reason(): void
    {
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();
        app(LoadService::class)->assignCrates($load, $this->crates(1), auth()->user());
        app(LoadService::class)->close($load->fresh(), auth()->user());

        $this->post(route('loads.reopen', $load), ['reason' => 'Faltó un pallet'])->assertForbidden();

        $this->actingAsRole('supervisor');
        $this->post(route('loads.reopen', $load), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('loads.reopen', $load), ['reason' => 'Faltó un pallet'])->assertRedirect();
        $this->assertSame(LoadStatus::Draft, $load->fresh()->status);
        $this->assertSame(1, Crate::query()->where('status', 'reserved')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'reopen', 'reason' => 'Faltó un pallet']);
    }

    public function test_cancel_releases_crates(): void
    {
        $this->actingAsRole('admin');
        $load = $this->newLoad();
        app(LoadService::class)->assignCrates($load, $this->crates(2), auth()->user());
        $this->post(route('loads.cancel', $load), ['reason' => 'Cliente suspendió el pedido'])->assertSessionHas('success');
        $this->assertSame(LoadStatus::Cancelled, $load->fresh()->status);
        $this->assertSame(0, Crate::query()->whereNotNull('current_load_id')->count());
    }

    public function test_full_flow_remito_checklist_dispatch_and_delivery(): void
    {
        Storage::fake('local');
        $user = $this->actingAsRole('admin');
        $load = $this->newLoad();
        $pallet = $this->pallet('PAL-9', 'with_product');
        foreach (['CJ-A', 'CJ-B'] as $code) {
            $this->crate($code, CrateStatus::Approved, ['pallet_id' => $pallet->id]);
        }
        $this->postJson(route('loads.crates.assign', $load), ['pallet_code' => 'PAL-9'])->assertOk()->assertJsonPath('assigned', 2);
        $this->assertSame('reserved', $pallet->fresh()->status->value);

        app(LoadService::class)->close($load->fresh(), $user);

        // Despachar sin checklist ni remito falla.
        $this->post(route('loads.dispatch', $load))->assertSessionHas('error');

        // Remito
        $this->post(route('remitos.store', $load))->assertRedirect();
        $remito = Remito::query()->firstOrFail();
        $this->assertSame('0001-00000001', $remito->number);
        $this->assertSame(2, $remito->total_crates);
        $this->post(route('remitos.store', $load))->assertSessionHas('error');

        $this->get(route('remitos.show', $remito))->assertOk();
        $pdf = $this->get(route('remitos.pdf', $remito))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));

        // QR público: datos limitados, sin CUIT.
        auth()->logout();
        $this->get(route('remitos.public', $remito->public_token))->assertOk()->assertSee($remito->number)->assertDontSee('30712345671')->assertDontSee('30-71234567-1');
        $this->get('/r/'.str_repeat('x', 40))->assertNotFound();
        $this->actingAs($user);

        // Checklist
        $this->get(route('loads.dispatch.show', $load))->assertOk();
        foreach (array_keys(DispatchCheck::ITEMS) as $item) {
            $this->post(route('loads.dispatch.check', $load), ['item' => $item, 'checked' => 1])->assertSessionHas('success');
        }
        $this->assertDatabaseHas('dispatch_checks', ['load_id' => $load->id, 'item' => 'plate', 'checked' => true, 'user_id' => $user->id]);

        $this->post(route('loads.dispatch', $load))->assertSessionHas('success');
        $this->assertSame(LoadStatus::Dispatched, $load->fresh()->status);
        $this->assertSame(2, Crate::query()->where('status', 'dispatched')->count());
        $this->assertSame('dispatched', $pallet->fresh()->status->value);
        $this->assertDatabaseHas('location_movements', ['movable_type' => 'pallet', 'to_label' => 'Camión AB123CD']);

        // Entrega con firma
        $png = 'data:image/png;base64,'.base64_encode($this->tinyPng(60, 20));
        $this->get(route('remitos.deliver.show', $remito))->assertOk();
        $this->post(route('remitos.deliver', $remito), ['receiver_name' => 'Ana Ruiz', 'receiver_dni' => '30.111.222', 'signature' => 'data:image/png;base64,AAAA'])
            ->assertSessionHas('error');
        $this->post(route('remitos.deliver', $remito), ['receiver_name' => 'Ana Ruiz', 'receiver_dni' => '30.111.222', 'signature' => $png])
            ->assertSessionHas('success');
        $this->assertSame('delivered', $remito->fresh()->status->value);
        $this->assertSame(LoadStatus::Delivered, $load->fresh()->status);
        $this->get(route('remitos.signature', $remito))->assertOk();
    }

    /** PNG válido generado sin ext-gd (firma de prueba). */
    private function tinyPng(int $w, int $h): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $raw = str_repeat(chr(0).str_repeat(chr(255).chr(255).chr(255), $w), $h);

        return chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10).$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw)).$chunk('IEND', '');
    }

    public function test_documents_upload_download_permissions(): void
    {
        Storage::fake('local');
        $this->actingAsRole('loads_operator');
        $load = $this->newLoad();

        $this->post(route('documents.store'), [
            'documentable_type' => 'load', 'documentable_id' => $load->id, 'type' => 'transport',
            'file' => UploadedFile::fake()->create('guia.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('success');
        $doc = \App\Models\Document::query()->firstOrFail();
        $this->get(route('documents.download', $doc))->assertOk();
        $this->get(route('documents.index'))->assertOk()->assertSee('guia');

        $this->post(route('documents.store'), [
            'documentable_type' => 'load', 'documentable_id' => $load->id, 'type' => 'other',
            'file' => UploadedFile::fake()->create('script.php', 5, 'text/x-php'),
        ])->assertSessionHasErrors('file');

        $this->actingAsRole('intake_operator');
        $this->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_permissions_and_module(): void
    {
        $this->actingAsRole('intake_operator');
        $this->get(route('loads.index'))->assertForbidden();

        $this->actingAsRole('admin');
        app(ModuleService::class)->setEnabled('loads', false);
        $this->get(route('loads.index'))->assertNotFound();
    }
}
