<?php

namespace Tests\Feature\Core;

use App\Models\Crate;
use App\Models\Packer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Las pantallas de impresión se abren en la misma ventana y siempre tienen «Volver» (en la app instalada no hay barra del navegador). */
class PrintBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_and_badge_pages_have_a_back_button_to_where_you_came_from(): void
    {
        $this->actingAsRole('admin');
        $crate = Crate::query()->create(['code' => 'CJ-900', 'status' => 'registered']);
        $packer = Packer::query()->create(['code' => 'EMB900', 'first_name' => 'Ana', 'last_name' => 'Paz', 'active' => true]);

        // Desde la ficha del cajón: «Volver» lleva a la ficha.
        $this->get(route('crates.show', $crate))->assertOk()->assertDontSee('target="_blank"', false);
        $this->from(route('crates.show', $crate))->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('Volver')->assertSee('href="'.route('crates.show', $crate).'"', false);

        // Abierta directamente: vuelve al listado.
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()->assertSee('Volver');
        $this->get(route('packers.badge', $packer))->assertOk()->assertSee('Volver');
    }
}
