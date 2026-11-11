<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** Campos de código (CJ-, PAL-, LOT-, CARG-, remito): el prefijo va solo y alcanza con escribir el número. */
class CodeInputsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_code_fields_carry_their_prefix_and_padding(): void
    {
        $this->actingAsRole('admin');
        $expect = [
            route('crates.index') => ['data-code-prefix="CJ-" data-code-padding="6"', 'data-code-prefix="PAL-"', 'data-code-prefix="CARG-"'],
            route('production.index') => ['data-code-prefix="CJ-"'],
            route('lots.index') => ['data-code-prefix="LOT-" data-code-padding="5"'],
            route('loads.index') => ['data-code-prefix="CARG-"'],
            route('remitos.index') => ['data-code-prefix="0001-" data-code-padding="8"'],
            route('crates.create') => ['data-code-prefix="CJ-"'],
            route('traceability.index') => ['data-code-prefix="CJ-" data-code-padding="6" data-code-addon="off"'],
        ];
        foreach ($expect as $url => $needles) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, "Falta {$needle} en {$url}");
            }
        }
    }

    public function test_pallet_search_by_number_finds_the_pallet_and_barcodes_still_work(): void
    {
        $this->actingAsRole('admin');
        $p = $this->pallet('PAL-000045');
        $p->update(['barcode' => '7791234567890']);
        $this->pallet('PAL-000046');

        $this->get(route('pallets.index', ['q' => '45']))->assertOk()->assertSee('PAL-000045')->assertDontSee('PAL-000046');
        $this->get(route('pallets.index', ['q' => '7791234567890']))->assertOk()->assertSee('PAL-000045')->assertDontSee('PAL-000046');
    }
}
