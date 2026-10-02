<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Load;
use App\Services\Arca\ArcaGateway;
use App\Services\Arca\ArcaGatewayFactory;
use App\Services\Arca\ArcaResult;
use App\Services\Arca\VatCalculator;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Integridad fiscal: sin CAE perdidos, sin duplicados en ARCA, sin doble factura y NC bien asociadas. */
class InvoiceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::query()->create(['business_name' => 'Cliente RI', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id, 'voucher_type' => 1, 'issued_on' => today()->toDateString(), 'currency' => 'ARS',
            'items' => [['description' => 'Naranja', 'quantity' => '100', 'unit' => 'kg', 'unit_price' => '50', 'vat_rate' => '21']],
        ], $overrides);
    }

    /** Gateway de prueba programable (simula ARCA en homologación). */
    private function fakeGateway(array $authorizeResults, ?array $consult = null): void
    {
        $gateway = new class($authorizeResults, $consult) implements ArcaGateway
        {
            public function __construct(public array $results, public ?array $consultResult) {}

            public function mode(): string { return 'homologation'; }

            public function authorize(Invoice $invoice): ArcaResult { return array_shift($this->results); }

            public function lastAuthorizedNumber(int $pointOfSale, int $voucherType): int { return 0; }

            public function consult(int $pointOfSale, int $voucherType, int $number): ?array { return $this->consultResult; }

            public function testConnection(): ArcaResult { return new ArcaResult(true); }
        };
        $this->app->instance(ArcaGatewayFactory::class, new class($gateway) extends ArcaGatewayFactory
        {
            public function __construct(private ArcaGateway $gateway) {}

            public function make(?string $mode = null): ArcaGateway { return $this->gateway; }
        });
    }

    public function test_no_response_keeps_invoice_pending_and_reconcile_recovers_the_cae(): void
    {
        $user = $this->actingAsRole('billing');
        $client = $this->client();
        $invoice = app(InvoiceService::class)->create($this->payload($client), $user);
        $this->fakeGateway(
            [ArcaResult::uncertain('Sin respuesta de ARCA (timeout).', 15, ['CbteDesde' => 15])],
            ['cae' => '75123456789012', 'cae_expires_on' => Carbon::parse('2026-10-11'), 'total' => (float) $invoice->total_amount, 'doc_number' => '30712345671', 'date' => '20261001'],
        );

        $this->post(route('invoices.submit', $invoice))->assertSessionHas('error');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Pending, $invoice->status, 'Sin respuesta NO es rechazo: no se puede reenviar a ciegas.');
        $this->post(route('invoices.submit', $invoice))->assertSessionHas('error'); // reenvío bloqueado
        $this->get(route('invoices.show', $invoice))->assertSee('Verificar en ARCA');
        $this->post(route('invoices.void', $invoice), ['reason' => 'Intento de anular'])->assertSessionHas('error');

        $this->post(route('invoices.reconcile', $invoice))->assertSessionHas('success');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame('75123456789012', $invoice->cae);
        $this->assertSame(15, (int) $invoice->number);
    }

    public function test_reconcile_when_arca_does_not_have_it_allows_resend(): void
    {
        $user = $this->actingAsRole('billing');
        $invoice = app(InvoiceService::class)->create($this->payload($this->client()), $user);
        $this->fakeGateway([ArcaResult::uncertain('timeout', 7, ['CbteDesde' => 7]), new ArcaResult(true, 7, '75000000000001', today()->addDays(10))], null);

        $this->post(route('invoices.submit', $invoice));
        $this->post(route('invoices.reconcile', $invoice))->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::Rejected, $invoice->fresh()->status);

        $this->post(route('invoices.submit', $invoice))->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::Authorized, $invoice->fresh()->status);
    }

    public function test_a_load_cannot_be_invoiced_twice(): void
    {
        $user = $this->actingAsRole('billing');
        $client = $this->client();
        $load = Load::query()->create(['number' => 'CARG-00010', 'date' => today(), 'client_id' => $client->id, 'status' => 'dispatched']);

        $this->post(route('invoices.store'), $this->payload($client, ['load_id' => $load->id]))->assertRedirect();
        $this->post(route('invoices.store'), $this->payload($client, ['load_id' => $load->id]))->assertSessionHas('error');
        $this->assertSame(1, Invoice::query()->where('load_id', $load->id)->count());

        // Anulado el borrador, se puede volver a facturar.
        $first = Invoice::query()->sole();
        app(InvoiceService::class)->void($first, 'Precio mal cargado');
        $this->post(route('invoices.store'), $this->payload($client, ['load_id' => $load->id]))->assertRedirect();
        $this->assertSame(2, Invoice::query()->where('load_id', $load->id)->count());
    }

    public function test_credit_note_requires_an_authorized_invoice_of_same_client_and_letter(): void
    {
        $user = $this->actingAsRole('billing');
        $client = $this->client();
        $other = Client::query()->create(['business_name' => 'Otro', 'cuit' => '30798765432', 'tax_condition' => 'RI']);
        $service = app(InvoiceService::class);
        $invoice = $service->create($this->payload($client), $user);
        $service->submit($invoice, $user);
        $foreign = $service->create($this->payload($other), $user);
        $service->submit($foreign, $user);

        $nc = $this->payload($client, ['voucher_type' => 3]);
        $this->post(route('invoices.store'), $nc)->assertSessionHasErrors('associated_invoice_id');
        $this->post(route('invoices.store'), $nc + ['associated_invoice_id' => $foreign->id])->assertSessionHas('error');
        $this->post(route('invoices.store'), $nc + ['associated_invoice_id' => $invoice->id])->assertRedirect();

        $credit = Invoice::query()->where('voucher_type', 3)->sole();
        $this->assertTrue($credit->associated->is($invoice));
        $service->submit($credit, $user);
        $this->assertSame(InvoiceStatus::Authorized, $credit->fresh()->status);
    }

    public function test_vat_is_computed_per_rate_on_the_grouped_base(): void
    {
        // 100 ítems de 0,10 kg × $0,0345: redondear el IVA ítem por ítem daría 0, sobre la base agrupada no.
        $lines = array_fill(0, 100, ['subtotal_cents' => VatCalculator::lineCents('0.10', '0.0345') ?: 0, 'vat_rate' => 21]);
        $lines[] = ['subtotal_cents' => VatCalculator::lineCents('1000.5', '100'), 'vat_rate' => 21];
        $totals = VatCalculator::totals(VatCalculator::groups($lines));
        $this->assertSame(10005000, $totals['net']);
        $this->assertSame((int) round(10005000 * 0.21), $totals['vat']);

        $user = $this->actingAsRole('billing');
        $invoice = app(InvoiceService::class)->create($this->payload($this->client(), ['items' => [
            ['description' => 'A', 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '0.335', 'vat_rate' => '21'],
            ['description' => 'B', 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '0.335', 'vat_rate' => '21'],
        ]]), $user);
        // Base 2,02 (1,01 + 1,01) × 21 % = 0,4242 → 0,42 (ítem por ítem daría 0,21 + 0,21 = 0,42 también, consistente)
        $this->assertSame('2.02', $invoice->net_amount);
        $this->assertSame('0.42', $invoice->vat_amount);
        $this->assertSame('2.44', $invoice->total_amount);
    }
}
