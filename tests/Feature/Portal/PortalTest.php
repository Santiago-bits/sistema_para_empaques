<?php

namespace Tests\Feature\Portal;

use App\Enums\CrateStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\Owner;
use App\Models\Remito;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private Client $mine;

    private Client $other;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleService::class)->setEnabled('client_portal', true);
        $this->mine = Client::query()->create(['business_name' => 'Frutas del Sur SA', 'cuit' => '30712345671', 'tax_condition' => 'RI']);
        $this->other = Client::query()->create(['business_name' => 'Competidor SRL', 'cuit' => '30798765432', 'tax_condition' => 'RI']);
    }

    private function portalUser(array $attributes): User
    {
        $user = User::factory()->role('client_portal')->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    private function load(Client $client, string $number, string $status = 'dispatched'): Load
    {
        return Load::query()->create(['number' => $number, 'date' => today(), 'client_id' => $client->id, 'status' => $status, 'total_crates' => 10, 'total_kg' => 185]);
    }

    private function invoice(Client $client, int $number, string $status = 'authorized'): Invoice
    {
        return Invoice::query()->create([
            'client_id' => $client->id, 'voucher_type' => 1, 'point_of_sale' => 1, 'number' => $number, 'issued_on' => today(),
            'total_amount' => 1000, 'status' => $status, 'cae' => $status === 'authorized' ? '99123456789012' : null,
        ]);
    }

    public function test_client_sees_only_own_loads_remitos_and_authorized_invoices(): void
    {
        $this->portalUser(['client_id' => $this->mine->id]);
        $myLoad = $this->load($this->mine, 'CARG-00001');
        $this->load($this->mine, 'CARG-00002', 'draft');
        $otherLoad = $this->load($this->other, 'CARG-00003');
        Remito::query()->create(['number' => '0001-00000001', 'load_id' => $myLoad->id, 'client_id' => $this->mine->id, 'issued_at' => now(), 'total_crates' => 10, 'total_kg' => 185, 'status' => 'issued', 'public_token' => Str::random(40)]);
        Remito::query()->create(['number' => '0001-00000002', 'load_id' => $otherLoad->id, 'client_id' => $this->other->id, 'issued_at' => now(), 'total_crates' => 10, 'total_kg' => 185, 'status' => 'issued', 'public_token' => Str::random(40)]);
        $this->invoice($this->mine, 11);
        $this->invoice($this->mine, 12, 'rejected');
        $this->invoice($this->other, 13);

        $this->get(route('portal.index'))->assertOk()->assertSee('Frutas del Sur SA')->assertSee('CARG-00001')
            ->assertDontSee('CARG-00002')->assertDontSee('CARG-00003');
        $this->get(route('portal.index', ['tab' => 'loads']))->assertOk()->assertSee('CARG-00001')->assertDontSee('CARG-00003');
        $this->get(route('portal.index', ['tab' => 'remitos']))->assertOk()->assertSee('0001-00000001')->assertDontSee('0001-00000002');
        $this->get(route('portal.index', ['tab' => 'invoices']))->assertOk()->assertSee('00000011')
            ->assertDontSee('00000012')->assertDontSee('00000013');
        // Pestaña de mercadería oculta para un cliente que no es propietario.
        $this->get(route('portal.index', ['tab' => 'stock']))->assertOk()->assertDontSee('Kg brutos');
    }

    public function test_invoice_pdf_only_for_own_authorized_invoices(): void
    {
        $this->portalUser(['client_id' => $this->mine->id]);
        $mine = $this->invoice($this->mine, 21);
        $draft = $this->invoice($this->mine, 22, 'draft');
        $foreign = $this->invoice($this->other, 23);

        $this->get(route('portal.invoices.pdf', $mine))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('portal.invoices.pdf', $draft))->assertNotFound();
        $this->get(route('portal.invoices.pdf', $foreign))->assertNotFound();
    }

    public function test_owner_sees_own_stock_only(): void
    {
        $owner = Owner::query()->create(['code' => 'OWN-1', 'name' => 'Propietario Uno']);
        $otherOwner = Owner::query()->create(['code' => 'OWN-2', 'name' => 'Propietario Dos']);
        $this->portalUser(['owner_id' => $owner->id]);
        $this->pallet('PAL-MIO')->update(['owner_id' => $owner->id]);
        $this->pallet('PAL-AJENO')->update(['owner_id' => $otherOwner->id]);
        $this->crate('CJ-MIO', CrateStatus::Approved, ['owner_id' => $owner->id]);
        $this->crate('CJ-AJENO', CrateStatus::Approved, ['owner_id' => $otherOwner->id]);

        $this->get(route('portal.index'))->assertOk()->assertSee('Cajones en galpón');
        $this->get(route('portal.index', ['tab' => 'stock']))->assertOk()->assertSee('PAL-MIO')->assertDontSee('PAL-AJENO');
    }

    public function test_unlinked_user_sees_nothing(): void
    {
        $this->load($this->mine, 'CARG-00001');
        $this->portalUser([]);
        $this->get(route('portal.index'))->assertOk()->assertSee('no está vinculado')->assertDontSee('CARG-00001');
    }

    public function test_portal_user_cannot_reach_internal_screens(): void
    {
        $this->portalUser(['client_id' => $this->mine->id]);
        $load = $this->load($this->mine, 'CARG-00001');
        $this->get(route('loads.show', $load))->assertForbidden();
        $this->get(route('crates.index'))->assertForbidden();
        $this->get(route('search', ['q' => 'CARG-00001']))->assertOk()->assertDontSee(route('loads.show', $load));
        $this->get(route('home'))->assertRedirect(route('portal.index'));
    }

    public function test_portal_is_404_when_module_disabled(): void
    {
        app(ModuleService::class)->setEnabled('client_portal', false);
        $this->portalUser(['client_id' => $this->mine->id]);
        $this->get(route('portal.index'))->assertNotFound();
    }
}
