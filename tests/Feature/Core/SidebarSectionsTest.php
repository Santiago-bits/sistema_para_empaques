<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Las secciones del menú lateral se despliegan: la de la pantalla actual abierta, las demás cerradas hasta que el usuario las abra. */
class SidebarSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_sections_are_collapsible_and_the_current_one_starts_open(): void
    {
        $this->actingAsRole('admin');

        $html = $this->get(route('loads.index'))->assertOk()->getContent();

        // Cada sección con título tiene su botón para desplegar y recuerda la elección del usuario.
        $this->assertStringContainsString("localStorage.getItem('galpon.menu.administracion')", $html);
        $this->assertStringContainsString("localStorage.getItem('galpon.menu.logistica')", $html);
        $this->assertMatchesRegularExpression('/aria-expanded="true"[^>]*>\s*<span class="truncate">Logística<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/aria-expanded="false"[^>]*>\s*<span class="truncate">Administración<\/span>/u', $html);
        // Los enlaces siguen en la página (se ocultan, no se quitan).
        $this->assertStringContainsString(route('loads.index'), $html);
    }
}
