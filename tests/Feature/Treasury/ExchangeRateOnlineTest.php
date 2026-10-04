<?php

namespace Tests\Feature\Treasury;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Valor del dólar traído de internet (oficial del Banco Nación o blue) y actualización automática cada 6 horas. */
class ExchangeRateOnlineTest extends TestCase
{
    use RefreshDatabase;

    private function fakeApi(): void
    {
        Http::fake([
            'dolarapi.com/v1/dolares/oficial' => Http::response(['casa' => 'oficial', 'compra' => 1400, 'venta' => 1450]),
            'dolarapi.com/v1/dolares/blue' => Http::response(['casa' => 'blue', 'compra' => 1480, 'venta' => 1500]),
        ]);
    }

    public function test_fetches_official_and_blue_rate(): void
    {
        $this->fakeApi();
        $user = $this->actingAsRole('billing');

        $this->post(route('exchange.fetch'), ['type' => 'oficial'])->assertSessionHas('success');
        $rate = ExchangeRate::current();
        $this->assertSame('1450.0000', $rate->sell);
        $this->assertSame('1400.0000', $rate->buy);
        $this->assertSame('BNA', $rate->source);
        $this->assertSame($user->id, $rate->user_id);

        // El mismo día se reemplaza, no se duplica.
        $this->post(route('exchange.fetch'), ['type' => 'blue'])->assertSessionHas('success');
        $this->assertSame(1, ExchangeRate::query()->count());
        $this->assertSame('1500.0000', ExchangeRate::current()->sell);
        $this->assertSame('BLUE', ExchangeRate::current()->source);

        $this->post(route('exchange.fetch'), ['type' => 'mep'])->assertSessionHasErrors('type');
    }

    public function test_service_down_shows_error_and_keeps_previous_value(): void
    {
        Http::fake(['dolarapi.com/*' => Http::response('caído', 503)]);
        $this->actingAsRole('billing');
        $this->post(route('exchange.store'), ['date' => today()->toDateString(), 'sell' => '1.300']);

        $this->post(route('exchange.fetch'), ['type' => 'oficial'])->assertSessionHas('error');
        $this->assertSame('1300.0000', ExchangeRate::current()->sell);
    }

    public function test_auto_update_every_six_hours_with_chosen_type(): void
    {
        $this->fakeApi();
        $this->actingAsRole('billing');

        // Apagada: no consulta nada.
        $this->get(route('exchange.index'))->assertOk()->assertSee('Actualización automática apagada');
        Http::assertNothingSent();

        // Al activarla con «blue» trae el valor enseguida.
        $this->post(route('exchange.auto'), ['enabled' => '1', 'type' => 'blue'])->assertSessionHas('success');
        $this->assertSame('1500.0000', ExchangeRate::current()->sell);
        $this->assertNull(ExchangeRate::current()->user_id);
        Http::assertSentCount(1);

        // Antes de 6 horas no vuelve a consultar; después sí.
        $service = app(ExchangeRateService::class);
        $this->travel(5)->hours();
        $this->assertNull($service->autoUpdate());
        $this->travel(2)->hours();
        $this->assertNotNull($service->autoUpdate());
        Http::assertSentCount(2);

        $this->get(route('exchange.index'))->assertOk()->assertSee('Se actualiza solo cada 6 horas')->assertSee('Dólar blue');

        // Apagada otra vez: no consulta más.
        $this->post(route('exchange.auto'), ['enabled' => '0', 'type' => 'oficial']);
        $this->travel(7)->hours();
        $this->assertNull($service->autoUpdate());
        Http::assertSentCount(2);
    }

    public function test_failed_auto_update_does_not_retry_on_every_visit(): void
    {
        Http::fake(['dolarapi.com/*' => Http::response('caído', 503)]);
        $this->actingAsRole('billing');
        $this->post(route('exchange.auto'), ['enabled' => '1', 'type' => 'oficial'])->assertSessionHas('success');

        $this->get(route('exchange.index'))->assertOk();
        $this->get(route('exchange.index'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_only_users_who_manage_exchange_can_fetch_or_configure(): void
    {
        $this->fakeApi();
        $this->actingAsRole('packer');
        $this->post(route('exchange.fetch'), ['type' => 'oficial'])->assertForbidden();
        $this->post(route('exchange.auto'), ['enabled' => '1', 'type' => 'oficial'])->assertForbidden();
        $this->assertFalse(ExchangeRateService::autoEnabled());
        Http::assertNothingSent();
    }
}
