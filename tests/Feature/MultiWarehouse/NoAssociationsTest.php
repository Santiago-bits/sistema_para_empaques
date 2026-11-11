<?php

namespace Tests\Feature\MultiWarehouse;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** Las asociaciones de citricultores se sacaron: no hay pantalla, ni pestaña, ni salen en lo que se imprime. */
class NoAssociationsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_associations_are_gone_everywhere(): void
    {
        $this->assertFalse(Route::has('superadmin.associations.index'));
        $this->actingAsRole('super_admin');
        $this->post(route('superadmin.confirm.store'), ['password' => 'password']);
        $this->get('/superadmin/asociaciones')->assertNotFound();
        $this->get(route('superadmin.index'))->assertOk()->assertDontSee('Asociaciones');
        $this->get(route('admin.settings.index'))->assertOk()->assertDontSee('Asociaciones de citricultores');
        $crate = $this->crate('CJ-000001');
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()->assertDontSee('class="associations"', false);
        // La tasa de asociación de Tesorería (descuento al productor) sigue: es otra cosa.
        $this->get(route('admin.settings.index', ['tab' => 'treasury']))->assertOk()->assertSee('Tasa de asociación');
    }
}
