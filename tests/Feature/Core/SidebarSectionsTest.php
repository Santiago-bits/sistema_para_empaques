<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menú en acordeón: apartados desplegables; el de la pantalla actual abierto, los demás cerrados. */
class SidebarSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_sections_are_collapsible_and_the_current_one_starts_open(): void
    {
        $this->actingAsRole('admin');

        $html = $this->get(route('loads.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-menu-section="logistica"', $html);
        $this->assertStringContainsString('data-menu-section="administracion"', $html);
        $this->assertMatchesRegularExpression('/aria-expanded="true" aria-controls="menu-logistica"/', $html);
        $this->assertMatchesRegularExpression('/aria-expanded="false" aria-controls="menu-administracion"/', $html);
        // Las opciones siguen en la página (se ocultan, no se quitan) y hay acceso directo a las etiquetas.
        $this->assertStringContainsString(route('loads.index'), $html);
        $this->assertStringContainsString(route('labels.crates'), $html);
    }
}
