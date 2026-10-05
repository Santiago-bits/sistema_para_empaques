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
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Necesita la extensión GD de PHP (para crear la imagen de prueba).');
        }
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

        // Pestaña del navegador y app instalada: íconos hechos con el logo, del tamaño justo.
        $page->assertSee(route('branding.icon', 'favicon-32', false), false)->assertSee(route('branding.icon', 'apple-touch-icon', false), false);
        $manifest = $this->get(route('app.manifest'))->assertOk()->json();
        $this->assertStringContainsString('/marca/icono/icon-512', $manifest['icons'][1]['src']);
        $this->assertSame('#A21616', $manifest['theme_color']);
        foreach (\App\Services\BrandingService::ICONS as $name => [$size]) {
            $response = $this->get(route('branding.icon', $name))->assertOk()->assertHeader('Content-Type', 'image/png');
            [$width, $height] = getimagesizefromstring($response->streamedContent());
            $this->assertSame([$size, $size], [$width, $height], $name);
        }

        // Otro logo: cambia la versión en la URL (el navegador no se queda con el ícono viejo).
        $before = app(\App\Services\BrandingService::class)->iconUrl('favicon-32');
        $this->post(route('admin.settings.update', 'company'), ['name' => 'Empaque del Valle', 'primary_color' => '#A21616',
            'logo' => UploadedFile::fake()->image('otro.jpg', 300, 100)]);
        $this->assertNotSame($before, app(\App\Services\BrandingService::class)->iconUrl('favicon-32'));
        $this->get(route('branding.icon', 'icon-512'))->assertOk();
        $this->assertCount(1, Storage::disk('public')->directories('branding/icons'));
    }

    public function test_without_logo_the_usual_icons_are_used(): void
    {
        User::factory()->role('admin')->create();
        $this->get(route('branding.icon', 'favicon-32'))->assertRedirect();
        $this->assertStringContainsString('/icons/icon-192.png', $this->get(route('app.manifest'))->json('icons.0.src'));
    }

    public function test_without_logo_the_route_is_not_found(): void
    {
        User::factory()->role('admin')->create();
        $this->get(route('branding.logo'))->assertNotFound();
    }
}
