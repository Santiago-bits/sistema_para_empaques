<?php

namespace Tests\Feature\Core;

use App\Models\ClientTicket;
use App\Models\License;
use App\Models\SupportTicket;
use App\Models\UsageReport;
use App\Services\Central\CentralSyncService;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentralPanelTest extends TestCase
{
    use RefreshDatabase;

    private function license(array $attributes = []): License
    {
        return License::query()->create($attributes + [
            'installation_id' => 'empaque-sur', 'client_name' => 'Empaque del Sur', 'license_key' => 'AAAA-BBBB-CCCC',
            'plan' => 'standard', 'starts_on' => today(), 'status' => 'active',
        ]);
    }

    private function headers(string $key = 'AAAA-BBBB-CCCC', string $installation = 'empaque-sur'): array
    {
        return ['Authorization' => 'Bearer '.$key, 'X-Installation-Id' => $installation];
    }

    public function test_api_requires_central_mode_and_a_valid_license(): void
    {
        $this->license();
        config(['galpon.central.mode' => false]);
        $this->postJson('/api/central/v1/reportes', ['metrics' => ['users_total' => 1]], $this->headers())->assertNotFound();

        config(['galpon.central.mode' => true]);
        $this->postJson('/api/central/v1/reportes', ['metrics' => ['users_total' => 1]], $this->headers('MAL'))->assertStatus(401);
        $this->postJson('/api/central/v1/reportes', ['metrics' => ['users_total' => 1]], $this->headers('AAAA-BBBB-CCCC', 'otro'))->assertStatus(401);
    }

    public function test_reports_keep_only_aggregate_metrics(): void
    {
        config(['galpon.central.mode' => true]);
        $license = $this->license();

        $this->postJson('/api/central/v1/reportes', ['version' => '1.2.0', 'metrics' => [
            'users_total' => 12, 'crates_30d' => 5400, 'modules' => ['loads', 'billing'], 'clientes' => ['Juan Pérez'], 'password' => 'x',
        ]], $this->headers())->assertOk();

        $report = UsageReport::query()->firstOrFail();
        $this->assertSame(12, $report->metric('users_total'));
        $this->assertArrayNotHasKey('clientes', $report->metrics);
        $this->assertArrayNotHasKey('password', $report->metrics);
        $this->assertSame('1.2.0', $license->fresh()->version);
        $this->assertTrue($license->fresh()->isOnline());
    }

    public function test_tickets_flow_both_ways_with_acknowledgement(): void
    {
        config(['galpon.central.mode' => true]);
        $license = $this->license();
        $payload = ['number' => 'TCK-1', 'subject' => 'Agregar reporte de romaneo', 'description' => 'Necesitamos un listado por productor',
            'priority' => 'high', 'requester' => 'Ana', 'replies' => [['id' => 7, 'author' => 'Ana', 'body' => 'Es urgente']]];

        $this->postJson('/api/central/v1/tickets', $payload, $this->headers())->assertOk();
        $this->postJson('/api/central/v1/tickets', $payload, $this->headers())->assertOk(); // reintento: no duplica
        $ticket = ClientTicket::query()->firstOrFail();
        $this->assertSame(1, $ticket->messages()->count());

        $this->actingAsRole('super_admin');
        $this->get(route('central.tickets.index'))->assertOk()->assertSee('Agregar reporte de romaneo');
        $this->post(route('central.tickets.reply', $ticket), ['body' => 'Lo agregamos en la próxima versión', 'status' => 'development'])->assertSessionHas('success');

        $updates = $this->getJson('/api/central/v1/novedades', $this->headers())->assertOk()->json();
        $this->assertSame('Lo agregamos en la próxima versión', $updates['messages'][0]['body']);
        $this->assertSame([['ticket' => 'TCK-1', 'status' => 'development']], $updates['statuses']);
        $this->assertSame('Empaque del Sur', $updates['license']['client_name']);

        // Sin confirmar, se vuelve a entregar; al confirmar, no.
        $this->assertCount(1, $this->getJson('/api/central/v1/novedades', $this->headers())->json('messages'));
        $this->postJson('/api/central/v1/novedades/recibidas', ['messages' => [$updates['messages'][0]['id']], 'tickets' => ['TCK-1']], $this->headers())->assertOk();
        $after = $this->getJson('/api/central/v1/novedades', $this->headers())->json();
        $this->assertSame([], $after['messages']);
        $this->assertSame([], $after['statuses']);
    }

    public function test_installation_syncs_round_trip_with_the_central_panel(): void
    {
        // Mismo proceso hace de empaque y de Panel General: las llamadas HTTP del empaque se atienden acá.
        $this->license(['installation_id' => 'local-dev']);
        config(['galpon.installation_id' => 'local-dev', 'galpon.central.url' => 'https://central.test', 'galpon.central.key' => 'AAAA-BBBB-CCCC']);
        Http::fake(function (HttpRequest $request) {
            config(['galpon.central.mode' => true]);
            $path = parse_url($request->url(), PHP_URL_PATH);
            $response = $this->call($request->method(), $path, [], [], [], $this->transformHeadersToServerVars([
                'Authorization' => $request->header('Authorization')[0] ?? '', 'X-Installation-Id' => $request->header('X-Installation-Id')[0] ?? '',
                'Accept' => 'application/json', 'Content-Type' => 'application/json',
            ]), $request->body());
            config(['galpon.central.mode' => false]);

            return Http::response($response->getContent(), $response->getStatusCode());
        });

        $user = $this->actingAsRole('admin');
        $local = app(SupportService::class)->create($user, ['subject' => 'Sumar campo de calibre', 'description' => 'En la etiqueta', 'priority' => 'medium']);
        app(SupportService::class)->reply($local, $user, 'Gracias');

        $sync = app(CentralSyncService::class);
        $this->assertTrue($sync->enabled());
        $result = $sync->sync(true);
        $this->assertTrue($result['report']);
        $this->assertSame(1, $result['tickets']);
        $this->assertSame(1, UsageReport::query()->count());

        $remote = ClientTicket::query()->where('remote_number', $local->number)->firstOrFail();
        $this->assertSame('Gracias', $remote->messages()->first()->body);

        // El proveedor responde desde el Panel General y el empaque lo recibe en la próxima sincronización.
        config(['galpon.central.mode' => true]);
        $this->actingAsRole('super_admin');
        $this->post(route('central.tickets.reply', $remote), ['body' => 'Listo, ya está', 'status' => 'resolved']);
        config(['galpon.central.mode' => false]);

        $second = $sync->sync();
        $this->assertSame(1, $second['messages']);
        $local->refresh();
        $this->assertSame('resolved', $local->status);
        $this->assertTrue($local->replies()->where('from_developer', true)->where('body', 'Listo, ya está')->exists());
        $this->assertNotNull($remote->messages()->where('from_developer', true)->first()->delivered_at);

        // Nada nuevo: no reenvía ni duplica.
        $third = $sync->sync();
        $this->assertSame(0, $third['tickets']);
        $this->assertSame(0, $third['messages']);
        $this->assertSame(2, SupportTicket::query()->firstOrFail()->replies()->count());
    }

    public function test_sync_failures_do_not_break_and_are_reported(): void
    {
        config(['galpon.central.url' => 'https://central.test', 'galpon.central.key' => 'K']);
        Http::fake(['*' => Http::response(['message' => 'caído'], 500)]);
        $this->artisan('galpon:central-sync')->assertFailed();

        $this->actingAsRole('admin');
        $this->get(route('support.index'))->assertOk()->assertSee('Último intento sin conexión');
        $this->post(route('support.sync'))->assertSessionHas('error');
    }

    public function test_central_pages_only_exist_in_central_mode(): void
    {
        $this->license();
        $this->actingAsRole('super_admin');
        config(['galpon.central.mode' => false]);
        $this->get(route('central.clients.index'))->assertNotFound();
        $this->get(route('dashboard'))->assertDontSee('Clientes y uso');

        config(['galpon.central.mode' => true]);
        $this->get(route('dashboard'))->assertSee('Clientes y uso');
        $this->get(route('central.clients.index'))->assertOk()->assertSee('Empaque del Sur');
        $this->get(route('central.clients.create'))->assertOk();
        $this->post(route('central.clients.store'), ['client_name' => 'Citrícola Norte', 'plan' => 'pro', 'status' => 'active', 'starts_on' => today()->toDateString(),
            'contact_name' => 'Raúl', 'modules' => ['loads', 'treasury']])->assertRedirect();
        $new = License::query()->where('client_name', 'Citrícola Norte')->firstOrFail();
        $this->assertStringStartsWith('citricola-norte-', $new->installation_id);
        $this->get(route('central.clients.show', $new))->assertOk()->assertSee('GALPON_LICENSE_KEY='.$new->license_key);

        // Un administrador del galpón (no super admin) no entra al Panel General.
        $this->actingAsRole('admin');
        $this->get(route('central.clients.index'))->assertForbidden();
    }
}
