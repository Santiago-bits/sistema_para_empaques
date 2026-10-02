<?php

namespace Tests\Feature\Core;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Supply;
use App\Models\Truck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Números escritos como en Argentina: «1.500» son mil quinientos, «1.234,56» lleva coma decimal. */
class NumberParsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_number_rules(): void
    {
        $cases = [
            ['1.500', true, '1500'], ['1.234,56', true, '1234.56'], ['1,5', true, '1.5'], ['125.000', true, '125000'],
            ['$ 1.000', true, '1000'], ['1,234.56', true, '1234.56'], ['92.61', true, '92.61'], ['1.234.567', true, '1234567'],
            ['18.5', false, '18.5'], ['18,40', false, '18.40'], ['-3,5', false, '-3.5'], ['', true, null],
        ];
        foreach ($cases as [$input, $thousands, $expected]) {
            $this->assertSame($expected, parse_number($input, $thousands), "«{$input}»");
        }
        $this->assertSame('abc', parse_number('abc'));
        $this->assertSame(12, parse_number(12));
    }

    public function test_supply_movement_with_thousands(): void
    {
        $this->actingAsRole('admin');
        $supply = Supply::query()->create(['code' => 'CAJ', 'name' => 'Caja', 'unit' => 'u', 'stock' => 2000, 'min_stock' => 0, 'active' => true]);

        $this->post(route('supplies.movements.store', $supply), ['type' => 'out', 'quantity' => '1.500', 'notes' => 'Pedido'])->assertRedirect();
        $this->assertEquals(500, (float) $supply->fresh()->stock);
    }

    public function test_invoice_items_with_thousands(): void
    {
        $this->actingAsRole('billing');
        $client = Client::query()->create(['business_name' => 'Cliente', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
        $this->post(route('invoices.store'), [
            'client_id' => $client->id, 'voucher_type' => 1, 'issued_on' => today()->toDateString(), 'currency' => 'ARS',
            'items' => [['description' => 'Naranja', 'quantity' => '1.000', 'unit' => 'kg', 'unit_price' => '1.250,50', 'vat_rate' => '21']],
        ])->assertRedirect();

        $this->assertSame('1250500.00', Invoice::query()->sole()->net_amount);
    }

    public function test_catalog_decimal_field_with_thousands(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('catalogs.trucks.store'), ['plate' => 'AE123FG', 'capacity_kg' => '28.000', 'active' => 1])->assertRedirect();
        $this->assertEquals(28000, (float) Truck::query()->where('plate', 'AE123FG')->value('capacity_kg'));
    }

    public function test_production_settings_targets_with_thousands(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('admin.settings.update', 'production'), [
            'weight_min' => '5', 'weight_max' => '30,5', 'scale_driver' => 'manual',
            'target_daily_kg' => '10.000', 'target_weekly_kg' => '60.000', 'target_monthly_kg' => '240.000',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(10000, (float) setting('production.target_daily_kg'));
        $this->assertEquals(30.5, (float) setting('production.weight_max'));
    }
}
