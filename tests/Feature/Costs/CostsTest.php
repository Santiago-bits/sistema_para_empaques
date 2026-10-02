<?php

namespace Tests\Feature\Costs;

use App\Models\Client;
use App\Models\Cost;
use App\Models\Invoice;
use App\Models\Load;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleService::class)->setEnabled('costs', true);
    }

    public function test_create_edit_and_delete_cost_with_argentine_amounts(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('costs.index'))->assertOk();
        $this->get(route('costs.create'))->assertOk();

        $this->post(route('costs.store'), ['category' => 'transport', 'description' => 'Flete Rosario', 'amount' => '125.000,50', 'date' => today()->toDateString()])
            ->assertRedirect(route('costs.index'));
        $cost = Cost::query()->sole();
        $this->assertSame('125000.50', $cost->amount);
        $this->assertSame('ARS', $cost->currency);

        $this->put(route('costs.update', $cost), ['category' => 'transport', 'description' => 'Flete Rosario', 'amount' => '130.000', 'date' => today()->toDateString()])
            ->assertRedirect();
        $this->assertSame('130000.00', $cost->fresh()->amount);
        $this->get(route('costs.edit', $cost))->assertOk()->assertSee('130.000,00');

        $this->delete(route('costs.destroy', $cost))->assertRedirect();
        $this->assertDatabaseMissing('costs', ['id' => $cost->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'cost', 'auditable_id' => $cost->id, 'action' => 'delete']);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('costs.store'), ['category' => 'hackeo', 'description' => '', 'amount' => '-5', 'date' => today()->addDay()->toDateString(), 'load_id' => 999])
            ->assertSessionHasErrors(['category', 'description', 'amount', 'date', 'load_id']);
        $this->assertSame(0, Cost::query()->count());
    }

    public function test_cost_can_be_linked_to_a_load(): void
    {
        $this->actingAsRole('admin');
        $client = Client::query()->create(['business_name' => 'Cliente SA']);
        $load = Load::query()->create(['number' => 'CARG-00009', 'date' => today(), 'client_id' => $client->id, 'status' => 'dispatched']);

        $this->post(route('costs.store'), ['category' => 'transport', 'description' => 'Flete', 'amount' => '1000', 'date' => today()->toDateString(), 'load_id' => $load->id]);
        $cost = Cost::query()->sole();
        $this->assertTrue($cost->costable->is($load));
        $this->get(route('costs.index'))->assertSee('CARG-00009');
    }

    public function test_profitability_requires_profit_permission_and_is_correct(): void
    {
        $client = Client::query()->create(['business_name' => 'Cliente SA', 'cuit' => '30712345671']);
        $invoice = fn (int $type, float $total, float $rate = 1, string $status = 'authorized') => Invoice::query()->create([
            'client_id' => $client->id, 'voucher_type' => $type, 'point_of_sale' => 1, 'issued_on' => today(),
            'total_amount' => $total, 'exchange_rate' => $rate, 'currency' => $rate > 1 ? 'USD' : 'ARS', 'status' => $status,
        ]);
        $invoice(1, 100000);          // factura A
        $invoice(1, 100, 1000);       // factura en dólares: 100.000 pesos
        $invoice(3, 20000);           // nota de crédito: resta
        $invoice(1, 999999, 1, 'draft'); // borrador: no cuenta
        Cost::query()->create(['category' => 'labor', 'description' => 'Jornales', 'amount' => 60000, 'date' => today()]);

        // Rol de facturación: ve costos pero no la rentabilidad.
        $this->actingAsRole('billing');
        $this->get(route('costs.index'))->assertOk()->assertDontSee('Rentabilidad del período');

        $this->actingAsRole('admin');
        $this->get(route('costs.index'))->assertOk()->assertSee('Rentabilidad del período')
            ->assertSee('$ 180.000,00')   // ingresos
            ->assertSee('$ 120.000,00');  // resultado
    }

    public function test_permissions_and_module(): void
    {
        $this->actingAsRole('packer');
        $this->get(route('costs.index'))->assertForbidden();

        $this->actingAsRole('quality');
        $this->post(route('costs.store'), ['category' => 'other', 'description' => 'x', 'amount' => '1', 'date' => today()->toDateString()])->assertForbidden();

        app(ModuleService::class)->setEnabled('costs', false);
        $this->actingAsRole('admin');
        $this->get(route('costs.index'))->assertNotFound();
    }
}
