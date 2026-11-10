<?php

namespace Tests\Feature\Core;

use App\Models\Crate;
use App\Models\Destination;
use App\Models\Load;
use App\Models\Variety;
use App\Services\LabelService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El código del galpón en ARCA sale en lo que se imprime; la etiqueta del cajón dice además a dónde va. */
class WarehouseArcaCodeOnDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_crate_and_pallet_labels_show_the_arca_code_and_the_destination(): void
    {
        $this->actingAsRole('admin');
        $variety = Variety::query()->create(['code' => 'VAL', 'name' => 'Valencia Late', 'active' => true]);
        $destination = Destination::query()->create(['name' => 'Mercado Central de Buenos Aires', 'active' => true]);
        $load = Load::query()->create(['number' => 'CARG-1', 'date' => today(), 'destination_id' => $destination->id, 'status' => 'draft']);
        $crate = Crate::query()->create(['code' => 'CJ-700', 'status' => 'registered', 'weight' => 18.5, 'variety_id' => $variety->id, 'current_load_id' => $load->id]);

        // Sin código cargado no aparece nada.
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()->assertDontSee('Galpón ARCA');

        app(SettingsService::class)->set('arca.warehouse_code', 'GAL-77');
        app(SettingsService::class)->set('label.show_company', false); // aunque se oculten los datos de la empresa

        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('Galpón ARCA N° GAL-77')
            ->assertSee('Valencia Late')->assertSee('18,5')
            ->assertSee('Destino')->assertSee('Mercado Central de Buenos Aires');
        $this->assertStringContainsString('GAL-77', (string) app(LabelService::class)->companyLine());

        // Un cajón que todavía no se cargó no inventa destino.
        $loose = Crate::query()->create(['code' => 'CJ-701', 'status' => 'registered', 'variety_id' => $variety->id]);
        $lines = collect(app(LabelService::class)->crateLabel($loose)['lines'])->pluck(0);
        $this->assertNotContains('Destino', $lines);
    }
}
