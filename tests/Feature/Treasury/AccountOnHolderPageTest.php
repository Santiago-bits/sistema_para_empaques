<?php

namespace Tests\Feature\Treasury;

use App\Models\Client;
use App\Models\Producer;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Al abrir la ficha de un cliente (o productor…) se ve directo su cuenta corriente. */
class AccountOnHolderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_page_shows_the_account_and_payments_return_to_it(): void
    {
        $user = $this->actingAsRole('admin');
        $client = Client::query()->create(['business_name' => 'Avana SRL', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
        app(AccountService::class)->adjust($client, 'opening_debit', 80000, 'Deuda anterior', null, $user);

        $page = route('catalogs.clients.show', $client);
        $this->get($page)->assertOk()
            ->assertSeeInOrder(['Cuenta corriente', 'Desde', 'Saldo anterior', 'Deuda anterior', money(80000), 'Datos'])
            // En la ficha sólo van el filtro y los movimientos: sin recuadros de saldo ni formularios (están en la cuenta corriente).
            ->assertDontSee('Saldo actual')->assertDontSee('Registrar cobro o pago')->assertDontSee('Ajuste o saldo inicial');
        $this->get(route('accounts.show', ['client', $client->id]))->assertOk()
            ->assertSee('Registrar cobro o pago')->assertSee('Ajuste o saldo inicial');

        // Cobro desde la ficha: vuelve a la ficha con el saldo actualizado.
        $this->from($page)->post(route('accounts.payments.store', ['client', $client->id]), [
            'direction' => 'collection', 'method' => 'transfer', 'amount' => '25.000', 'date' => today()->toDateString(),
        ])->assertRedirect($page)->assertSessionHas('success');
        $this->get($page)->assertOk()->assertSee(money(55000))->assertSee('Cobro');

        // Filtro de fechas en la misma ficha.
        $this->get(route('catalogs.clients.show', [$client, 'from' => today()->addDay()->toDateString(), 'to' => today()->addDays(2)->toDateString()]))
            ->assertOk()->assertSee('Sin movimientos en el período.');

        // La pantalla propia de la cuenta corriente sigue igual.
        $this->get(route('accounts.show', ['client', $client->id]))->assertOk()->assertSee(money(55000))->assertSee('Imprimir resumen');
    }

    public function test_producer_page_also_shows_the_account(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Finca Uno']);
        $this->get(route('catalogs.producers.show', $producer))->assertOk()->assertSee('Cuenta corriente')->assertSee('Sin movimientos en el período.');
    }

    public function test_without_treasury_permission_the_account_is_not_shown(): void
    {
        $client = Client::query()->create(['business_name' => 'Avana SRL', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
        $this->actingWithPermissions(['catalogs.view']);
        $this->get(route('catalogs.clients.show', $client))->assertOk()->assertDontSee('Saldo anterior')->assertDontSee('Registrar cobro o pago');
    }

    public function test_client_list_opens_the_page_by_clicking_the_name_or_the_row(): void
    {
        $this->actingAsRole('admin');
        $client = Client::query()->create(['business_name' => 'Avana SRL', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
        $url = route('catalogs.clients.show', $client);

        $this->get(route('catalogs.clients.index'))->assertOk()
            ->assertSee('data-href="'.$url.'"', false)
            ->assertSee('<a href="'.$url.'" class="font-medium', false);
    }
}
