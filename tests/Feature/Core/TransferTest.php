<?php

namespace Tests\Feature\Core;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Importar y exportar (Excel)»: un solo lugar, a la vista en el menú, con lo que cada uno puede bajar o subir. */
class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finds_everything_to_download_and_upload(): void
    {
        $this->actingAsRole('admin');
        Driver::query()->create(['first_name' => 'Carlos', 'last_name' => 'Díaz', 'dni' => '25111222']);

        $this->get(route('dashboard'))->assertSee('Importar y exportar');
        $page = $this->get(route('transfer.index'))->assertOk()
            ->assertSee('Bajar Excel')->assertSee('Subir Excel')->assertSee('Planilla modelo')
            ->assertSee('Camioneros')->assertSee('Clientes')->assertSee('Proveedores')
            ->assertSee('Producción (cajón por cajón)')->assertSee('Cheques');

        // Los enlaces bajan de verdad un Excel.
        $this->get(route('catalogs.drivers.index').'?format=xlsx')->assertOk()
            ->assertHeader('content-disposition');
        $this->get(route('imports.create', ['type' => 'drivers']))->assertOk();
    }

    public function test_user_without_any_related_permission_cannot_open_it(): void
    {
        $this->actingWithPermissions(['production.scan']);
        $this->get(route('transfer.index'))->assertForbidden();
    }

    public function test_viewer_without_import_permission_only_downloads(): void
    {
        $this->actingWithPermissions(['catalogs.view']);
        $this->get(route('transfer.index'))->assertOk()->assertSee('Bajar Excel')->assertDontSee('Subir Excel');
    }
}
