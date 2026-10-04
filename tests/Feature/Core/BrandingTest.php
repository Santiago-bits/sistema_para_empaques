<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Configuración → Empresa: el logo y el color principal se ven en todo el sistema. */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_is_served_without_public_storage_link_and_color_tints_the_interface(): void
    {
        Storage::fake('public');
        $this->actingAsRole('admin');

        // Color por defecto: no se agrega nada.
        $this->get(route('dashboard'))->assertOk()->assertDontSee('--color-brand-600:', false);

        $this->post(route('admin.settings.update', 'company'), [
            'name' => 'Empaque del Valle',
            'primary_color' => '#A21616',
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ])->assertSessionHas('success');

        $page = $this->get(route('dashboard'))->assertOk();
        $page->assertSee('--color-brand-600: #a21616', false)->assertSee('<meta name="theme-color" content="#a21616">', false);

        // El logo sale de una ruta propia (Hostinger no permite el enlace public/storage).
        $page->assertSee(route('branding.logo', [], false), false)->assertDontSee('/storage/branding', false);
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_without_logo_the_route_is_not_found(): void
    {
        User::factory()->role('admin')->create();
        $this->get(route('branding.logo'))->assertNotFound();
    }
}
