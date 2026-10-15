<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El menú no muestra el mapa ni las ubicaciones del galpón: sólo el código del galpón en ARCA, que carga el dueño. */
class WarehouseArcaCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_has_no_map_or_locations_and_shows_the_arca_code_set_by_the_owner(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('dashboard'))->assertOk()
            ->assertDontSee('Mapa del galpón')
            ->assertDontSee(route('locations.index'), false)
            ->assertDontSee('Código ARCA:');

        $this->post(route('admin.settings.update', 'arca'), [
            'mode' => 'simulation', 'point_of_sale' => 1, 'cuit' => '', 'emitter_condition' => 'RI',
            'warehouse_code' => '  GAL-00123  ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('GAL-00123', setting('arca.warehouse_code'));
        $this->get(route('admin.settings.index', ['tab' => 'arca']))->assertOk()
            ->assertSee('Código del galpón (ARCA)')->assertSee('GAL-00123');
        $this->get(route('dashboard'))->assertOk()->assertSee('Código ARCA: GAL-00123');
    }
}
