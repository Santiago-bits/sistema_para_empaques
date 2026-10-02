<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShortcutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_shortcut_points_to_an_existing_route(): void
    {
        foreach (config('shortcuts.functions') as $key => $fn) {
            $this->assertTrue(Route::has($fn['route']), "{$key} → {$fn['route']}");
        }
        $letters = [];
        foreach (config('shortcuts.groups') as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (isset($item['go'])) {
                    $this->assertTrue(Route::has($item['go']), implode('+', $item['keys']).' → '.$item['go']);
                    $this->assertNotContains($item['keys'][1], $letters, 'Letra repetida: G + '.$item['keys'][1]);
                    $letters[] = $item['keys'][1];
                }
            }
        }
    }

    public function test_help_page_lists_shortcuts_for_any_user(): void
    {
        $this->actingAsRole('packer');

        $this->get(route('help.shortcuts'))
            ->assertOk()
            ->assertSee('Ayuda y atajos de teclado')
            ->assertSee('Ctrl')
            ->assertSee('Ir a la tabla (primera fila)');
    }

    public function test_shortcuts_only_include_sections_the_user_can_open(): void
    {
        $this->actingAsRole('packer');

        $page = $this->get(route('help.shortcuts'))->assertOk();
        $page->assertDontSee('Facturación');
        $page->assertDontSee(route('invoices.index'), false);

        $this->actingAsRole('admin');
        $this->get(route('help.shortcuts'))->assertOk()->assertSee('Facturación');
    }

    public function test_layout_exposes_bindings_modal_and_menu_entry(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-global-search', false)
            ->assertSee('Atajos de teclado')
            ->assertSee(route('help.shortcuts'), false)
            // Js::from escapa las comillas como ".
            ->assertSee(chr(92).'u0022F3'.chr(92).'u0022', false)
            ->assertSee('cargas'.chr(92).'u0022', false);
    }

    public function test_kiosk_users_cannot_open_help_page(): void
    {
        $this->actingAsRole('packer', ['kiosk_mode' => true]);

        $this->get(route('help.shortcuts'))->assertRedirect();
    }
}
