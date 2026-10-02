<?php

namespace Tests\Feature\Treasury;

use App\Enums\LoadStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountMovement;
use App\Models\CashMovement;
use App\Models\Check;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\Provider;
use App\Models\Transporter;
use App\Services\AccountService;
use App\Services\CashService;
use App\Services\CheckService;
use App\Services\InvoiceService;
use App\Services\ModuleService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::query()->create(['business_name' => 'Frutas del Sur SA', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
    }

    private function balance($holder): float
    {
        return app(AccountService::class)->balance($holder);
    }

    public function test_permissions_and_disabled_module(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('cash.index'))->assertForbidden();
        $this->get(route('accounts.index'))->assertForbidden();

        $this->actingAsRole('supervisor');
        $this->get(route('cash.index'))->assertOk();
        $this->post(route('cash.open'), ['opening_balance' => '1000'])->assertForbidden();

        app(ModuleService::class)->setEnabled('treasury', false);
        $this->actingAsRole('admin');
        $this->get(route('checks.index'))->assertNotFound();
    }

    public function test_cash_open_move_and_close_with_count(): void
    {
        $this->actingAsRole('billing');

        $this->post(route('cash.open'), ['opening_balance' => '10.000,00'])->assertSessionHas('success');
        $this->post(route('cash.open'), ['opening_balance' => '5'])->assertSessionHas('error');

        $this->post(route('cash.movements.store'), ['direction' => 'in', 'category' => 'other', 'description' => 'Venta de descarte', 'amount' => '2.500,50'])
            ->assertSessionHas('success');
        $this->post(route('cash.movements.store'), ['direction' => 'out', 'category' => 'expenses', 'description' => 'Combustible', 'amount' => '99999'])
            ->assertSessionHas('error');
        $this->post(route('cash.movements.store'), ['direction' => 'out', 'category' => 'expenses', 'description' => 'Combustible', 'amount' => '500'])
            ->assertSessionHas('success');

        $session = app(CashService::class)->current();
        $this->assertSame(12000.5, $session->totals()['balance']);
        $this->get(route('cash.index'))->assertOk()->assertSee('Combustible')->assertSee('Saldo actual');

        // Diferencia sin motivo → rechazado; con motivo → se cierra y queda registrada.
        $this->post(route('cash.close'), ['counted_balance' => '12.000'])->assertSessionHas('error');
        $this->post(route('cash.close'), ['counted_balance' => '12.000', 'notes' => 'Faltante de cambio'])->assertRedirect();
        $session->refresh();
        $this->assertFalse($session->isOpen());
        $this->assertSame('-0.50', $session->difference);
        $this->get(route('cash.show', $session))->assertOk();
        $this->get(route('cash.sessions'))->assertOk();

        // Saldo anterior sugerido = lo contado.
        $this->assertSame(12000.0, app(CashService::class)->suggestedOpening());
    }

    public function test_cash_collection_posts_to_account_and_void_cascades(): void
    {
        $user = $this->actingAsRole('admin');
        $client = $this->client();
        app(AccountService::class)->adjust($client, 'opening_debit', 100000, 'Saldo inicial', null, $user);

        $this->post(route('accounts.payments.store', ['client', $client->id]), ['direction' => 'collection', 'method' => 'cash', 'amount' => '40.000', 'date' => today()->toDateString()])
            ->assertSessionHas('error'); // caja cerrada

        app(CashService::class)->open(0, null, $user);
        $this->post(route('accounts.payments.store', ['client', $client->id]), ['direction' => 'collection', 'method' => 'cash', 'amount' => '40.000', 'date' => today()->toDateString()])
            ->assertSessionHas('success');

        $this->assertSame(60000.0, $this->balance($client));
        $cash = CashMovement::query()->firstOrFail();
        $this->assertSame('in', $cash->direction);
        $this->assertSame(40000.0, app(CashService::class)->current()->totals()['balance']);

        // Anular el movimiento de caja anula también el cobro en la cuenta.
        $this->post(route('cash.movements.void', $cash), ['reason' => 'Se cargó dos veces'])->assertSessionHas('success');
        $this->assertSame(100000.0, $this->balance($client));
        $this->assertSame(0.0, app(CashService::class)->current()->totals()['balance']);

        $this->get(route('accounts.show', ['client', $client->id]))->assertOk()->assertSee('Frutas del Sur SA');
        $this->get(route('accounts.show', ['client', $client->id, 'anulados' => 1]))->assertOk()->assertSee('Anulado');
        $this->get(route('accounts.print', ['client', $client->id]))->assertOk()->assertSee('Resumen de cuenta corriente');
        $this->get(route('accounts.index', ['type' => 'client', 'only' => 'debtors']))->assertOk()->assertSee('Frutas del Sur SA');
        $this->get(route('accounts.index', ['type' => 'client', 'format' => 'xlsx']))->assertOk();
    }

    public function test_check_collection_endorsement_and_rejection(): void
    {
        $user = $this->actingAsRole('billing');
        $client = $this->client();
        $provider = Provider::query()->create(['name' => 'Cartonera Norte']);
        $accounts = app(AccountService::class);

        $this->post(route('accounts.payments.store', ['client', $client->id]), [
            'direction' => 'collection', 'method' => 'check', 'amount' => '250.000', 'date' => today()->toDateString(),
            'check' => ['bank' => 'Banco Nación', 'number' => '123', 'payment_date' => today()->addDays(10)->toDateString()],
        ])->assertSessionHas('success');
        $check = Check::query()->firstOrFail();
        $this->assertSame('in_portfolio', $check->status);
        $this->assertSame(-250000.0, $this->balance($client));

        // Endoso al proveedor: el importe sale del cheque.
        $this->post(route('accounts.payments.store', ['provider', $provider->id]), [
            'direction' => 'payment', 'method' => 'check', 'endorse_check_id' => $check->id, 'date' => today()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertSame('endorsed', $check->fresh()->status);
        $this->assertSame(250000.0, $this->balance($provider));

        // Un segundo endoso del mismo cheque no se permite.
        $this->expectsBusinessError(fn () => $accounts->registerPayment($provider, [
            'direction' => 'payment', 'method' => 'check', 'endorse_check_id' => $check->id, 'date' => today()->toDateString(),
        ], $user));

        // Rechazo sin motivo → error; con motivo: el cliente vuelve a deber y al proveedor se le vuelve a deber.
        $this->post(route('checks.transition', $check), ['status' => 'rejected'])->assertSessionHas('error');
        $this->post(route('checks.transition', $check), ['status' => 'rejected', 'notes' => 'Sin fondos'])->assertSessionHas('success');
        $this->assertSame(0.0, $this->balance($client));
        $this->assertSame(0.0, $this->balance($provider));
        $this->assertSame('rejected', $check->fresh()->status);

        $this->get(route('checks.show', $check))->assertOk()->assertSee('Sin fondos', false);
        $this->get(route('checks.index', ['view' => 'rejected']))->assertOk()->assertSee('Banco Nación');
        $this->get(route('checks.index', ['view' => 'all', 'format' => 'xlsx']))->assertOk();
    }

    public function test_check_states_and_standalone_check(): void
    {
        $user = $this->actingAsRole('billing');
        $this->get(route('checks.create'))->assertOk();
        $this->post(route('checks.store'), [
            'kind' => 'third_party', 'bank' => 'Galicia', 'number' => 'A1', 'amount' => '10.000',
            'issued_on' => today()->toDateString(), 'payment_date' => today()->toDateString(),
        ])->assertRedirect();
        $check = Check::query()->firstOrFail();

        // Duplicado del mismo banco y número.
        $this->post(route('checks.store'), [
            'kind' => 'third_party', 'bank' => 'Galicia', 'number' => 'A1', 'amount' => '5',
            'issued_on' => today()->toDateString(), 'payment_date' => today()->toDateString(),
        ])->assertSessionHas('error');

        $this->post(route('checks.transition', $check), ['status' => 'paid'])->assertSessionHas('error');
        $this->post(route('checks.transition', $check), ['status' => 'deposited'])->assertSessionHas('success');
        $this->post(route('checks.transition', $check), ['status' => 'cashed'])->assertSessionHas('success');
        $this->assertSame('cashed', $check->fresh()->status);

        // Concurrencia: un cheque ya movido con una versión vieja no se puede volver a mover.
        $stale = Check::query()->create(['kind' => 'own', 'bank' => 'Macro', 'number' => '9', 'amount' => 10, 'issued_on' => today(),
            'payment_date' => today(), 'status' => 'issued', 'created_by' => $user->id]);
        $copy = clone $stale;
        app(CheckService::class)->transition($stale, 'paid', $user);
        $this->expectsBusinessError(fn () => app(CheckService::class)->transition($copy, 'voided', $user, 'x'));
    }

    public function test_lot_settlement_with_association_fee(): void
    {
        $user = $this->actingAsRole('admin');
        app(SettingsService::class)->set('treasury.association_fee_per_kg', 2);
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Finca Los Álamos']);
        $lot = Lot::query()->create(['code' => 'LOT-9', 'date' => today(), 'producer_id' => $producer->id, 'status' => 'open']);

        $this->post(route('lots.settle', $lot))->assertSessionHas('error'); // falta kilos y precio

        $this->put(route('lots.update', $lot), ['date' => today()->toDateString(), 'producer_id' => $producer->id, 'kg_received' => '10.000', 'price_per_kg' => '150,50'])
            ->assertSessionHasNoErrors();
        $this->get(route('lots.show', $lot))->assertOk()->assertSee('Liquidar al productor');

        $this->post(route('lots.settle', $lot))->assertSessionHas('success');
        // 10.000 kg × 150,50 = 1.505.000 a favor; tasa 2 $/kg = 20.000 al debe → le debemos 1.485.000.
        $this->assertSame(-1485000.0, $this->balance($producer));
        $this->post(route('lots.settle', $lot))->assertSessionHas('error');

        // Con el lote liquidado no se pueden cambiar los kilos.
        $this->put(route('lots.update', $lot), ['date' => today()->toDateString(), 'producer_id' => $producer->id, 'kg_received' => '9000', 'price_per_kg' => '150,50'])
            ->assertSessionHas('error');

        // Anular la liquidación anula compra y tasa, y el lote se puede volver a liquidar.
        $purchase = AccountMovement::query()->where('type', 'purchase')->firstOrFail();
        $this->post(route('accounts.movements.void', $purchase), ['reason' => 'Precio mal cargado'])->assertSessionHas('success');
        $this->assertSame(0.0, $this->balance($producer));
        $this->assertNull($lot->fresh()->settled_at);
        app(AccountService::class)->settleLot($lot->fresh(), $user);
        $this->assertSame(-1485000.0, $this->balance($producer));
    }

    public function test_authorized_invoices_and_freight_post_once(): void
    {
        Http::fake();
        $user = $this->actingAsRole('admin');
        $client = $this->client();
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'client_id' => $client->id, 'voucher_type' => 1, 'issued_on' => today()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 1000,
            'items' => [['description' => 'Limón', 'quantity' => 100, 'unit' => 'kg', 'unit_price' => 10, 'vat_rate' => 21]],
        ], $user);
        $invoices->submit($invoice, $user);

        // Simulación: no impacta en la cuenta mientras no se habilite.
        $this->assertSame(0.0, $this->balance($client));
        app(SettingsService::class)->set('treasury.post_test_invoices', true);
        $this->post(route('accounts.sync'))->assertSessionHas('success');
        // US$ 1.210 × 1.000 = $ 1.210.000
        $this->assertSame(1210000.0, $this->balance($client));
        app(AccountService::class)->syncPending();
        $this->assertSame(1, AccountMovement::query()->where('source_type', 'invoice')->count());

        // Producción: se imputa sin configuración extra.
        app(SettingsService::class)->set('treasury.post_test_invoices', false);
        Invoice::query()->whereKey($invoice->id)->update(['arca_mode' => 'production']);
        $this->assertNotNull(app(AccountService::class)->postInvoice($invoice->fresh()));

        $transporter = Transporter::query()->create(['business_name' => 'Transportes Ruta 22']);
        $load = Load::query()->create(['number' => 'CAR-77', 'date' => today(), 'status' => LoadStatus::Dispatched, 'transporter_id' => $transporter->id,
            'freight_amount' => 350000, 'dispatched_at' => now()]);
        app(AccountService::class)->postFreight($load);
        app(AccountService::class)->postFreight($load->fresh());
        $this->assertSame(-350000.0, $this->balance($transporter));
        $this->assertNotNull($load->fresh()->freight_posted_at);
    }

    public function test_exchange_rate_is_saved_and_shown_in_header(): void
    {
        $this->actingAsRole('billing');
        $this->post(route('exchange.store'), ['date' => today()->toDateString(), 'sell' => '1.234,50', 'buy' => '1.200', 'source' => 'BNA'])
            ->assertSessionHas('success');
        $this->assertSame('1234.5000', ExchangeRate::current()->sell);
        $this->post(route('exchange.store'), ['date' => today()->toDateString(), 'sell' => '1.240'])->assertSessionHas('success');
        $this->assertSame(1, ExchangeRate::query()->count());

        $this->get(route('dashboard'))->assertOk()->assertSee('US$')->assertSee('1.240,00');
        $this->get(route('exchange.index'))->assertOk();

        $this->actingAsRole('packer');
        $this->get(route('production.mine'))->assertDontSee('US$');
    }

    private function expectsBusinessError(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba un error de negocio.');
        } catch (BusinessException) {
            $this->addToAssertionCount(1);
        }
    }
}
