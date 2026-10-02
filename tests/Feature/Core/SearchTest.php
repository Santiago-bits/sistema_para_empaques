<?php

namespace Tests\Feature\Core;

use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_exact_code_opens_the_record_directly(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-000123');
        $pallet = $this->pallet('PAL-000045');

        $this->get(route('search', ['q' => 'cj-000123']))->assertRedirect(route('crates.show', $crate));
        $this->get(route('search', ['q' => 'PAL-000045']))->assertRedirect(route('pallets.show', $pallet));
    }

    public function test_partial_term_lists_grouped_results(): void
    {
        $this->actingAsRole('admin');
        $this->crate('CJ-000123');
        $this->crate('CJ-000124');
        $this->producer('PROD-9')->update(['name' => 'Finca Los Álamos', 'cuit' => '20304050607']);

        $this->get(route('search', ['q' => 'CJ-0001']))->assertOk()->assertSee('Cajones')->assertSee('CJ-000123')->assertSee('CJ-000124');
        $this->get(route('search', ['q' => 'Álamos']))->assertOk()->assertSee('Finca Los Álamos');
        $this->get(route('search', ['q' => '20-30405060-7']))->assertOk()->assertSee('Finca Los Álamos');
        $this->get(route('search', ['q' => 'zzzz']))->assertOk()->assertSee('No encontramos nada');
        $this->get(route('search', ['q' => 'a']))->assertOk()->assertSee('al menos 2');
    }

    public function test_results_respect_permissions_and_modules(): void
    {
        $crate = $this->crate('CJ-000123');
        $this->actingWithPermissions(['catalogs.view']);
        $this->get(route('search', ['q' => 'CJ-0001']))->assertOk()->assertDontSee(route('crates.show', $crate));

        $this->actingAsRole('admin');
        $this->get(route('search', ['q' => 'CJ-0001']))->assertOk()->assertSee(route('crates.show', $crate));
        app(ModuleService::class)->setEnabled('crates', false);
        $this->get(route('search', ['q' => 'CJ-0001']))->assertOk()->assertDontSee(route('crates.show', $crate));
        $this->get(route('search', ['q' => 'CJ-000123']))->assertOk();
    }

    public function test_like_wildcards_are_escaped_and_html_is_escaped(): void
    {
        $this->actingAsRole('admin');
        $this->crate('CJ-000123');
        $this->get(route('search', ['q' => '%%']))->assertOk()->assertDontSee('CJ-000123');
        $this->get(route('search', ['q' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
