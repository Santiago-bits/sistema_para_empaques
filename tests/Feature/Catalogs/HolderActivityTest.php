<?php

namespace Tests\Feature\Catalogs;

use App\Models\AccountMovement;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Models\Driver;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Producer;
use App\Services\AccountService;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ficha de movimientos de cada cliente, productor y camionero: plata, bines y envases (préstamo,
 * devolución o parte de pago), fruta que ingresó y cargas. Incluye pruebas de permisos y seguridad.
 */
class HolderActivityTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $name = 'Frutas del Sur SA', string $cuit = '30712345671'): Client
    {
        return Client::query()->create(['business_name' => $name, 'cuit' => $cuit, 'tax_condition' => 'RI']);
    }

    private function bin(): ContainerType
    {
        return ContainerType::query()->create(['code' => 'BIN', 'name' => 'Bin plástico', 'kind' => 'bin']);
    }

    private function store(string $type, int $id, array $data)
    {
        return $this->post(route('holders.containers.store', [$type, $id]), $data + ['reason' => 'loan', 'date' => today()->toDateString()]);
    }

    private function balance(Client|Producer $holder): float
    {
        return app(AccountService::class)->balance($holder);
    }

    public function test_lending_and_returning_bins_updates_the_container_balance_but_not_the_money(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client();
        $bin = $this->bin();

        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 20, 'container_type_id' => $bin->id])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 8, 'container_type_id' => $bin->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, ContainerMovement::query()->count());
        $this->assertSame(0, AccountMovement::query()->count());
        $this->assertSame(0.0, $this->balance($client));

        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()
            ->assertSee('Frutas del Sur SA')
            ->assertSee('Se le entregaron 20 envases (Bin plástico)')
            ->assertSee('Entregó 8 envases (Bin plástico)')
            ->assertSeeInOrder(['Bines y envases', '12', 'Bin plástico', 'Los tiene él (son nuestros)']);

        // Queda en el historial de cambios.
        $this->assertTrue(AuditLog::query()->where('action', 'containers_delivered')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'containers_received')->exists());
    }

    public function test_client_pays_part_of_the_debt_with_bins(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client();
        app(AccountService::class)->adjust($client, 'opening_debit', 100000, 'Saldo inicial', null, auth()->user());

        $this->store('client', $client->id, [
            'direction' => 'received', 'quantity' => 10, 'container_type_id' => $this->bin()->id,
            'reason' => 'payment', 'unit_price' => '2500',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $movement = ContainerMovement::query()->firstOrFail();
        $this->assertSame('25000.00', $movement->amount);
        $account = AccountMovement::query()->where('type', 'containers')->firstOrFail();
        $this->assertSame($account->id, $movement->account_movement_id);
        $this->assertSame('25000.00', $account->credit);
        $this->assertSame(75000.0, $this->balance($client)); // 100.000 − 10 × 2.500

        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()
            ->assertSee('Pago con envases')->assertSee('Nos debe')->assertSee(money(75000));
        $this->get(route('accounts.show', ['client', $client->id]))->assertOk()->assertSee('Pago con envases')->assertSee('Ver envases');
    }

    public function test_we_pay_a_producer_with_bins_and_it_is_charged_to_his_account(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Finca Uno']);

        $this->store('producer', $producer->id, [
            'direction' => 'delivered', 'quantity' => 4, 'reason' => 'payment', 'unit_price' => '30000,50',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Le entregamos envases como pago: es como un pago → debe.
        $this->assertSame(120002.0, $this->balance($producer));
        $this->assertSame('120002.00', AccountMovement::query()->value('debit'));
    }

    public function test_voiding_a_bin_payment_voids_the_account_movement_too_and_vice_versa(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client();
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 2, 'reason' => 'payment', 'unit_price' => '1000']);
        $movement = ContainerMovement::query()->firstOrFail();

        $this->post(route('holders.containers.void', ['client', $client->id, $movement->id]), ['reason' => 'Error de carga'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($movement->fresh()->voided_at);
        $this->assertNotNull(AccountMovement::query()->firstOrFail()->voided_at);
        $this->assertSame(0.0, $this->balance($client));

        // Anular dos veces no se puede.
        $this->post(route('holders.containers.void', ['client', $client->id, $movement->id]), ['reason' => 'De nuevo'])
            ->assertSessionHas('error');

        // Desde la cuenta corriente: anula también los envases.
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 3, 'reason' => 'payment', 'unit_price' => '1000']);
        $second = ContainerMovement::query()->whereNull('voided_at')->firstOrFail();
        $this->post(route('accounts.movements.void', $second->account_movement_id), ['reason' => 'Anulado desde la cuenta'])->assertRedirect();
        $this->assertNotNull($second->fresh()->voided_at);
        $this->assertSame(0, app(\App\Services\ContainerMovementService::class)->balances($client)->count());
    }

    public function test_bin_payment_cannot_be_corrected_from_the_account(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client();
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 2, 'reason' => 'payment', 'unit_price' => '1000']);
        $account = AccountMovement::query()->firstOrFail();

        $this->put(route('accounts.movements.correct', $account), [
            'amount' => '5000', 'date' => today()->toDateString(), 'description' => 'x', 'reason' => 'Cambio de precio',
        ])->assertSessionHas('error');
        $this->assertSame('2000.00', $account->fresh()->credit);
    }

    public function test_drivers_can_borrow_bins_but_not_pay_with_them(): void
    {
        $this->actingAsRole('admin');
        $driver = Driver::query()->create(['first_name' => 'Carlos', 'last_name' => 'Díaz', 'dni' => '25111222']);

        $this->store('driver', $driver->id, ['direction' => 'delivered', 'quantity' => 5])->assertSessionHasNoErrors();
        $this->store('driver', $driver->id, ['direction' => 'received', 'quantity' => 1, 'reason' => 'payment', 'unit_price' => '100'])
            ->assertSessionHas('error');
        $this->assertSame(1, ContainerMovement::query()->count());
        $this->assertSame(0, AccountMovement::query()->count());

        $this->get(route('holders.activity', ['driver', $driver->id]))->assertOk()
            ->assertDontSee('Como parte de pago')->assertSee('Se le entregaron 5 envases');
    }

    public function test_activity_lists_money_bins_fruit_and_loads_together_and_filters_by_kind_and_date(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Finca Uno']);
        $driver = Driver::query()->create(['first_name' => 'Carlos', 'last_name' => 'Díaz', 'dni' => '25111222']);
        $client = $this->client();
        Lot::query()->create(['code' => 'ING-001', 'date' => today(), 'producer_id' => $producer->id, 'driver_id' => $driver->id, 'status' => 'open', 'bins' => 14, 'quantity' => 0, 'kg_received' => 5600]);
        Lot::query()->create(['code' => 'ING-ANULADO', 'date' => today(), 'producer_id' => $producer->id, 'status' => 'voided', 'bins' => 99]);
        Load::query()->create(['number' => 'CARG-1', 'date' => today(), 'client_id' => $client->id, 'driver_id' => $driver->id, 'status' => 'dispatched', 'total_crates' => 40, 'total_kg' => 740]);
        Load::query()->create(['number' => 'CARG-CANCEL', 'date' => today(), 'client_id' => $client->id, 'status' => 'cancelled', 'total_crates' => 1, 'total_kg' => 1]);
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 6, 'date' => today()->subMonths(2)->toDateString()]);
        app(AccountService::class)->registerPayment($client, ['direction' => 'collection', 'amount' => 5000, 'method' => 'transfer'], auth()->user());

        $this->get(route('holders.activity', ['producer', $producer->id]))->assertOk()
            ->assertSee('ING-001')->assertSee('14 bines')->assertDontSee('ING-ANULADO')->assertSee('Fruta que ingresó');
        $this->get(route('holders.activity', ['driver', $driver->id]))->assertOk()->assertSee('ING-001')->assertSee('CARG-1');
        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()
            ->assertSee('CARG-1')->assertDontSee('CARG-CANCEL')->assertSee('Se le entregaron 6 envases')->assertSee('Cobro');

        // Filtros: sólo cargas; sólo el último mes.
        $this->get(route('holders.activity', ['client', $client->id, 'kind' => 'loads']))->assertOk()
            ->assertSee('CARG-1')->assertDontSee('Se le entregaron 6 envases');
        $this->get(route('holders.activity', ['client', $client->id, 'from' => today()->subMonth()->toDateString()]))->assertOk()
            ->assertSee('CARG-1')->assertDontSee('Se le entregaron 6 envases');
        $this->get(route('holders.activity', ['client', $client->id, 'kind' => 'otra']))->assertSessionHasErrors('kind');

        // La ficha del catálogo lleva a los movimientos.
        $this->get(route('catalogs.clients.show', $client))->assertOk()->assertSee(route('holders.activity', ['client', $client->id]), false);
    }

    public function test_validation_rejects_bad_quantities_directions_and_missing_price(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client();

        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => -5])->assertSessionHasErrors('quantity');
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 'diez'])->assertSessionHasErrors('quantity');
        $this->store('client', $client->id, ['direction' => 'robado', 'quantity' => 1])->assertSessionHasErrors('direction');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1, 'reason' => 'payment'])->assertSessionHasErrors('unit_price');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1, 'reason' => 'payment', 'unit_price' => '-3'])->assertSessionHasErrors('unit_price');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1, 'container_type_id' => 999])->assertSessionHasErrors('container_type_id');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1, 'date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');
        $this->assertSame(0, ContainerMovement::query()->count());
    }

    public function test_permissions_are_enforced(): void
    {
        $client = $this->client();

        // Sin permiso: no ve la ficha de movimientos.
        $this->actingWithPermissions(['catalogs.view']);
        $this->get(route('holders.activity', ['client', $client->id]))->assertForbidden();
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 1])->assertForbidden();
        $this->get(route('catalogs.clients.show', $client))->assertOk()->assertDontSee(route('holders.activity', ['client', $client->id]), false);

        // Sólo ver: no puede registrar ni anular; tampoco ve la plata si no tiene tesorería.
        $this->actingWithPermissions(['catalogs.view', 'containers.view']);
        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()
            ->assertDontSee('Registrar bines o envases')->assertDontSee('Nos debe')->assertDontSee('Está al día');
        $this->get(route('holders.activity', ['client', $client->id, 'kind' => 'money']))->assertForbidden();
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 1])->assertForbidden();
        $this->assertSame(0, ContainerMovement::query()->count());

        // Los roles por defecto: el operador de ingreso registra envases; el embalador no entra.
        $this->actingAsRole('intake_operator');
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 1])->assertSessionHasNoErrors();
        $this->actingAsRole('packer');
        $this->get(route('holders.activity', ['client', $client->id]))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $client = $this->client();
        $this->get(route('holders.activity', ['client', $client->id]))->assertRedirect(route('login'));
        $this->post(route('holders.containers.store', ['client', $client->id]), ['direction' => 'delivered', 'quantity' => 1])->assertRedirect(route('login'));
        $this->assertSame(0, ContainerMovement::query()->count());
    }

    public function test_cannot_void_another_holders_movement_or_use_unknown_types(): void
    {
        $this->actingAsRole('admin');
        $mine = $this->client();
        $other = $this->client('Competidor SRL', '30798765432');
        $this->store('client', $other->id, ['direction' => 'delivered', 'quantity' => 3]);
        $movement = ContainerMovement::query()->firstOrFail();

        // Anular el movimiento de otro cliente usando la URL del mío: no existe.
        $this->post(route('holders.containers.void', ['client', $mine->id, $movement->id]), ['reason' => 'Intento'])->assertNotFound();
        $this->assertNull($movement->fresh()->voided_at);

        // Tipos de titular que no corresponden (usuarios, roles…) o registros inexistentes: 404.
        $this->get('/fichas/user/1/movimientos')->assertNotFound();
        $this->get('/fichas/role/1/movimientos')->assertNotFound();
        $this->get(route('holders.activity', ['client', 999999]))->assertNotFound();
        $this->post(route('holders.containers.void', ['client', $other->id, 999999]), ['reason' => 'No existe'])->assertNotFound();
        // El motivo de anulación es obligatorio.
        $this->post(route('holders.containers.void', ['client', $other->id, $movement->id]), ['reason' => ''])->assertSessionHasErrors('reason');
    }

    public function test_text_is_escaped_to_prevent_script_injection(): void
    {
        $this->actingAsRole('admin');
        $client = $this->client('<script>alert(1)</script> SA');
        $this->store('client', $client->id, ['direction' => 'delivered', 'quantity' => 1, 'description' => '<img src=x onerror=alert(2)>']);

        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertSee('&lt;img src=x onerror=alert(2)&gt;', false);
    }

    public function test_without_treasury_bins_work_but_payment_with_bins_is_not_offered(): void
    {
        $this->actingAsRole('admin');
        app(ModuleService::class)->setEnabled('treasury', false);
        $client = $this->client();

        $this->get(route('holders.activity', ['client', $client->id]))->assertOk()->assertDontSee('Como parte de pago')->assertDontSee('Nos debe');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1, 'reason' => 'payment', 'unit_price' => '10'])
            ->assertSessionHas('error');
        $this->store('client', $client->id, ['direction' => 'received', 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, ContainerMovement::query()->count());
    }
}
