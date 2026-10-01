<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Arca\ArcaGatewayFactory;
use App\Services\InvoiceService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $condition = 'RI', ?string $cuit = '30712345671'): Client
    {
        return Client::query()->create(['business_name' => 'Cliente '.$condition, 'cuit' => $cuit, 'tax_condition' => $condition]);
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'voucher_type' => 1,
            'issued_on' => today()->toDateString(),
            'currency' => 'ARS',
            'items' => [
                ['description' => 'Naranja 70', 'quantity' => '1.000,50', 'unit' => 'kg', 'unit_price' => '100', 'vat_rate' => '21'],
                ['description' => 'Flete', 'quantity' => '1', 'unit' => 'u', 'unit_price' => '500', 'vat_rate' => '10.5'],
            ],
        ], $overrides);
    }

    public function test_pages_render(): void
    {
        $this->actingAsRole('billing');
        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('invoices.create'))->assertOk();
        $this->get(route('arca.index'))->assertOk()->assertSee('Simulación');
    }

    public function test_totals_are_calculated_on_server(): void
    {
        $this->actingAsRole('billing');
        $this->post(route('invoices.store'), $this->payload($this->client()) + ['total_amount' => 1, 'net_amount' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        // 1000.50 × 100 = 100050 (21 %) + 500 (10,5 %)
        $this->assertSame('100550.00', $invoice->net_amount);
        $this->assertSame('21063.00', $invoice->vat_amount);
        $this->assertSame('121613.00', $invoice->total_amount);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->get(route('invoices.show', $invoice))->assertOk();
        $this->get(route('invoices.edit', $invoice))->assertOk();
        $this->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_simulation_authorizes_without_network_and_numbers_sequentially(): void
    {
        Http::fake();
        $user = $this->actingAsRole('billing');
        $client = $this->client();
        $a = app(InvoiceService::class)->create($this->payload($client), $user);
        $b = app(InvoiceService::class)->create($this->payload($client), $user);

        $this->post(route('invoices.submit', $a))->assertSessionHas('success');
        $this->post(route('invoices.submit', $b))->assertSessionHas('success');

        $a->refresh();
        $b->refresh();
        $this->assertSame(InvoiceStatus::Authorized, $a->status);
        $this->assertSame(1, (int) $a->number);
        $this->assertSame(2, (int) $b->number);
        $this->assertStringStartsWith('99', $a->cae);
        $this->assertSame('simulation', $a->arca_mode);
        $this->assertDatabaseHas('arca_records', ['invoice_id' => $a->id, 'status' => 'success']);
        Http::assertNothingSent();

        $this->get(route('invoices.pdf', $a))->assertOk();
    }

    public function test_double_submit_does_not_duplicate(): void
    {
        $user = $this->actingAsRole('billing');
        $invoice = app(InvoiceService::class)->create($this->payload($this->client()), $user);

        app(InvoiceService::class)->submit($invoice, $user);
        $this->expectException(BusinessException::class);
        app(InvoiceService::class)->submit($invoice->fresh(), $user);
    }

    public function test_production_mode_blocked_outside_production_environment(): void
    {
        Http::fake();
        $user = $this->actingAsRole('billing');
        app(SettingsService::class)->set('arca.mode', 'production');
        $invoice = app(InvoiceService::class)->create($this->payload($this->client()), $user);

        $this->post(route('invoices.submit', $invoice))->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_homologation_without_certificates_is_rejected_clearly(): void
    {
        Http::fake();
        $user = $this->actingAsRole('billing');
        app(SettingsService::class)->set('arca.mode', 'homologation');
        app(SettingsService::class)->set('arca.cuit', '20123456786');
        config(['galpon.arca.certificate_path' => null]);
        $invoice = app(InvoiceService::class)->create($this->payload($this->client()), $user);

        $this->post(route('invoices.submit', $invoice))->assertSessionHas('error');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Rejected, $invoice->status);
        $this->assertDatabaseHas('arca_records', ['invoice_id' => $invoice->id, 'status' => 'error']);
    }

    public function test_homologation_parses_arca_response(): void
    {
        $user = $this->actingAsRole('billing');
        app(SettingsService::class)->set('arca.mode', 'homologation');
        app(SettingsService::class)->set('arca.cuit', '20123456786');
        \Illuminate\Support\Facades\Cache::put('arca:ta:homologation:20123456786', ['token' => 'T', 'sign' => 'S', 'cuit' => '20123456786'], 3600);

        Http::fake(function ($request) {
            $action = $request->header('SOAPAction')[0] ?? '';
            if (str_contains($action, 'FECompUltimoAutorizado')) {
                return Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><FECompUltimoAutorizadoResponse xmlns="http://ar.gov.afip.dif.FEV1/"><FECompUltimoAutorizadoResult><PtoVta>1</PtoVta><CbteTipo>1</CbteTipo><CbteNro>41</CbteNro></FECompUltimoAutorizadoResult></FECompUltimoAutorizadoResponse></soap:Body></soap:Envelope>');
            }

            return Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><FECAESolicitarResponse xmlns="http://ar.gov.afip.dif.FEV1/"><FECAESolicitarResult><FeCabResp><Resultado>A</Resultado></FeCabResp><FeDetResp><FECAEDetResponse><Resultado>A</Resultado><CAE>75123456789012</CAE><CAEFchVto>20261015</CAEFchVto></FECAEDetResponse></FeDetResp></FECAESolicitarResult></FECAESolicitarResponse></soap:Body></soap:Envelope>');
        });

        $invoice = app(InvoiceService::class)->create($this->payload($this->client()), $user);
        $this->post(route('invoices.submit', $invoice))->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(42, (int) $invoice->number);
        $this->assertSame('75123456789012', $invoice->cae);
        $this->assertSame('2026-10-15', $invoice->cae_expires_on->toDateString());
        Http::assertSent(fn ($r) => str_contains($r->body(), '<ar:CondicionIVAReceptorId>1</ar:CondicionIVAReceptorId>')
            && str_contains($r->body(), '<ar:ImpTotal>121613.00</ar:ImpTotal>') || str_contains($r->header('SOAPAction')[0] ?? '', 'Ultimo'));
        $this->assertDatabaseMissing('arca_records', ['request' => '%"Token"%']);
    }

    public function test_factura_a_requires_cuit_and_voided_rules(): void
    {
        $user = $this->actingAsRole('billing');
        $invoice = app(InvoiceService::class)->create($this->payload($this->client('RI', null)), $user);
        $this->post(route('invoices.submit', $invoice))->assertSessionHas('error');

        $this->post(route('invoices.void', $invoice), ['reason' => 'Error de cliente'])->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::Voided, $invoice->fresh()->status);

        $authorized = app(InvoiceService::class)->create($this->payload($this->client('RI', '30712345671')), $user);
        app(InvoiceService::class)->submit($authorized, $user);
        $this->post(route('invoices.void', $authorized), ['reason' => 'Intento de anular'])->assertSessionHas('error');
    }

    public function test_voucher_type_by_tax_condition(): void
    {
        $service = app(InvoiceService::class);
        $this->assertSame(1, $service->voucherTypeFor($this->client('RI')));
        $this->assertSame(6, $service->voucherTypeFor($this->client('CF', null)));
        app(SettingsService::class)->set('arca.emitter_condition', 'MT');
        $this->assertSame(11, $service->voucherTypeFor($this->client('RI')));
    }

    public function test_permissions(): void
    {
        $this->actingAsRole('loads_operator');
        $this->get(route('invoices.index'))->assertForbidden();
        $this->actingAsRole('intake_operator');
        $this->get(route('arca.index'))->assertForbidden();
    }

    public function test_factory_never_uses_production_in_testing(): void
    {
        $this->expectException(BusinessException::class);
        app(ArcaGatewayFactory::class)->make('production');
    }
}
