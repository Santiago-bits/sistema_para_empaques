<?php

namespace Tests\Feature\Api;

use App\Models\ColdRoom;
use App\Models\User;
use App\Services\Devices\ApiScaleReader;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function bearer(User $user, array $abilities): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test', $abilities)->plainTextToken];
    }

    public function test_requires_token_and_always_answers_json(): void
    {
        User::factory()->role('admin')->create();
        $this->get('/api/v1/me')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
        $this->get('/api/v1/me', ['Authorization' => 'Bearer 1|inventado'])->assertStatus(401);
    }

    public function test_read_endpoints(): void
    {
        $admin = User::factory()->role('admin')->create();
        $headers = $this->bearer($admin, ['read']);
        $this->crate('CJ-000777', 'processed');
        $this->pallet('PAL-000001');

        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('data.username', $admin->username)->assertJsonPath('data.abilities', ['read']);
        $this->getJson('/api/v1/cajones?status=processed', $headers)->assertOk()->assertJsonPath('data.0.code', 'CJ-000777')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/cajones/cj-000777', $headers)->assertOk()->assertJsonPath('data.weight_kg', 18.5);
        $this->getJson('/api/v1/cajones/NO-EXISTE', $headers)->assertNotFound();
        $this->getJson('/api/v1/pallets/PAL-000001', $headers)->assertOk()->assertJsonPath('data.code', 'PAL-000001');
        $this->getJson('/api/v1/cargas', $headers)->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->getJson('/api/v1/insumos', $headers)->assertOk();
        $this->getJson('/api/v1/produccion/hoy', $headers)->assertOk()->assertJsonStructure(['data' => ['crates_processed', 'kg_processed', 'loads_dispatched']]);
    }

    public function test_token_abilities_are_enforced(): void
    {
        $admin = User::factory()->role('admin')->create();
        $scaleOnly = $this->bearer($admin, ['scale:write']);
        $this->getJson('/api/v1/cajones', $scaleOnly)->assertForbidden();
        $this->postJson('/api/v1/sensores/lecturas', ['sensor_key' => 'X', 'temperature' => 2], $scaleOnly)->assertForbidden();
    }

    public function test_token_never_exceeds_its_user_permissions(): void
    {
        $packer = User::factory()->role('packer')->create(); // no tiene crates.view ni loads.view
        $headers = $this->bearer($packer, ['read']);
        $this->getJson('/api/v1/cajones', $headers)->assertForbidden();
        $this->getJson('/api/v1/cargas', $headers)->assertForbidden();
    }

    public function test_inactive_user_token_and_disabled_module_are_blocked(): void
    {
        $admin = User::factory()->role('admin')->create();
        $headers = $this->bearer($admin, ['read']);
        app(ModuleService::class)->setEnabled('loads', false);
        $this->getJson('/api/v1/cargas', $headers)->assertNotFound();

        $admin->update(['status' => 'inactive']);
        $this->app['auth']->forgetGuards(); // en producción cada request resuelve el usuario de cero
        $this->getJson('/api/v1/me', $headers)->assertForbidden();
    }

    public function test_scale_reading_is_stored_for_the_scan_screen(): void
    {
        $operator = User::factory()->role('intake_operator')->create();
        $headers = $this->bearer($operator, ['scale:write']);

        $this->postJson('/api/v1/balanza/lecturas', ['station' => 'linea-1', 'weight' => 18450, 'unit' => 'g', 'stable' => true], $headers)
            ->assertCreated()->assertJsonPath('data.weight', 18.45);
        $this->assertSame(18.45, (new ApiScaleReader)->read('linea-1')['weight']);
        $this->assertSame(18.45, Cache::get(ApiScaleReader::cacheKey('linea-1'))['weight']);

        $this->postJson('/api/v1/balanza/lecturas', ['station' => 'l1', 'weight' => 6000], $headers)->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->postJson('/api/v1/balanza/lecturas', ['station' => '../x', 'weight' => -1], $headers)->assertUnprocessable()->assertJsonValidationErrors(['station', 'weight']);
    }

    public function test_sensor_reading_records_temperature_and_raises_alert(): void
    {
        app(ModuleService::class)->setEnabled('cold_rooms', true);
        $admin = User::factory()->role('admin')->create();
        $room = ColdRoom::query()->create(['code' => 'CAM-1', 'name' => 'Cámara 1', 'sensor_key' => 'SENS-01', 'temp_min' => 0, 'temp_max' => 4, 'active' => true]);
        $headers = $this->bearer($admin, ['sensors:write']);

        $this->postJson('/api/v1/sensores/lecturas', ['sensor_key' => 'SENS-01', 'temperature' => 2.5, 'humidity' => 90], $headers)
            ->assertCreated()->assertJsonPath('data.out_of_range', false);
        $this->postJson('/api/v1/sensores/lecturas', ['sensor_key' => 'SENS-01', 'temperature' => 9], $headers)
            ->assertCreated()->assertJsonPath('data.out_of_range', true);
        $this->assertDatabaseHas('alerts', ['type' => 'temperature', 'severity' => 'critical']);
        $this->postJson('/api/v1/sensores/lecturas', ['sensor_key' => 'NO-EXISTE', 'temperature' => 2], $headers)->assertNotFound();
        $this->assertSame(2, $room->readings()->count());
    }

    public function test_token_management_screen(): void
    {
        $user = $this->actingAsRole('super_admin');
        $device = User::factory()->role('intake_operator')->create(['username' => 'balanza1']);

        $this->get(route('admin.tokens.index'))->assertOk();
        $response = $this->post(route('admin.tokens.store'), ['name' => 'Balanza línea 1', 'abilities' => ['scale:write'], 'owner_id' => $device->id]);
        $plain = $response->getSession()->get('plain_token');
        $this->assertNotEmpty($plain);
        $this->assertSame(1, $device->tokens()->count());
        $this->assertDatabaseMissing('audit_logs', ['new_values' => $plain]);

        $token = $device->tokens()->sole();
        $this->delete(route('admin.tokens.destroy', $token->id))->assertRedirect();
        $this->assertSame(0, $device->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/balanza/lecturas', ['station' => 'l1', 'weight' => 10], ['Authorization' => 'Bearer '.$plain])->assertUnauthorized();
    }

    public function test_non_super_admin_only_manages_own_tokens(): void
    {
        $other = User::factory()->role('admin')->create();
        $foreign = $other->createToken('ajeno', ['read'])->accessToken;

        $this->actingAsRole('admin');
        $this->get(route('admin.tokens.index'))->assertOk()->assertDontSee('ajeno');
        $this->delete(route('admin.tokens.destroy', $foreign->id))->assertNotFound();
        $this->post(route('admin.tokens.store'), ['name' => 'x', 'abilities' => ['read'], 'owner_id' => $other->id])->assertSessionHas('error');
    }
}
