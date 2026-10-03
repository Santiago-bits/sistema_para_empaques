<?php

namespace Tests\Feature\Core;

use App\Models\Client;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Clientes, productos (variedades) y demás fichas a la vista en el menú, sin buscarlos dentro de otra pantalla. */
class MenuFichasTest extends TestCase
{
    use RefreshDatabase;

    public function test_shed_owner_finds_clients_and_products_in_the_menu_and_can_add_them(): void
    {
        $this->actingAsRole('admin');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Fichas')->assertSee('Clientes')->assertSee('Productos (variedades)')
            ->assertSee('Productores')->assertSee('Proveedores')->assertSee('Camioneros')->assertSee('Todas las fichas');

        $this->get(route('catalogs.varieties.create'))->assertOk()->assertSee('Nuevo producto');
        $this->post(route('catalogs.varieties.store'), ['code' => 'NAR-VAL', 'name' => 'Naranja Valencia', 'species' => 'Naranja', 'active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Variety::query()->where('name', 'Naranja Valencia')->exists());

        $this->get(route('catalogs.clients.create'))->assertOk();
        $this->post(route('catalogs.clients.store'), ['business_name' => 'Mercado Central', 'cuit' => '30500001735', 'tax_condition' => 'RI', 'active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Client::query()->where('business_name', 'Mercado Central')->exists());
    }
}
