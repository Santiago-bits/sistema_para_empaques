<?php

namespace Tests\Feature\Core;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** App instalable (escritorio y celular): ficha con el nombre de la empresa, íconos y botón «Instalar app». */
class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_has_the_company_name_icons_and_opens_as_a_window(): void
    {
        app(SettingsService::class)->set('company.name', 'Empaque Los Álamos');

        $manifest = $this->get(route('app.manifest'))->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')->json();

        $this->assertSame('Empaque Los Álamos', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $sizes = collect($manifest['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim(strtok($icon['src'], '?'), '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_every_layout_links_the_manifest_and_offers_install(): void
    {
        \App\Models\User::factory()->create();
        $this->get(route('login'))->assertOk()->assertSee('rel="manifest"', false);

        $this->actingAsRole('admin');
        $this->get(route('dashboard'))->assertOk()->assertSee('rel="manifest"', false)->assertSee('Instalar app');
        $this->get(route('help.shortcuts'))->assertOk()->assertSee('Instalar el sistema como app');
    }

    public function test_manifest_is_reachable_before_installation(): void
    {
        // Sin usuarios todo redirige al asistente, salvo la ficha de la app.
        \App\Models\User::query()->forceDelete();
        $this->get(route('app.manifest'))->assertOk();
    }
}
