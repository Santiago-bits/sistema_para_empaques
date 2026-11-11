<?php

namespace Tests\Feature\Core;

use App\Models\Alert;
use App\Models\Module;
use App\Models\Permission;
use App\Services\ModuleService;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Las cámaras de frío se sacaron: no hay menú, pantallas, permisos, sensores ni alertas de temperatura. */
class NoColdRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cold_rooms_are_gone_even_if_a_warehouse_had_them_enabled(): void
    {
        // Un galpón viejo con la parte prendida, permisos y una alerta abierta de temperatura.
        Module::query()->updateOrCreate(['key' => 'cold_rooms'], ['name' => 'Cámaras', 'enabled' => true, 'is_core' => false, 'sort' => 99]);
        Permission::query()->create(['slug' => 'cold_rooms.view', 'name' => 'Ver cámaras', 'module' => 'cold_rooms']);
        Alert::query()->create(['type' => 'temperature', 'severity' => 'critical', 'title' => 'Temperatura fuera de rango', 'fingerprint' => 't:1']);
        app(ModuleService::class)->flush();

        // Al actualizar (datos base) se limpia todo.
        $this->seed(SystemSeeder::class);
        $this->assertFalse(app(ModuleService::class)->enabled('cold_rooms'));
        $this->assertFalse(Permission::query()->where('module', 'cold_rooms')->exists());
        $this->assertSame(0, Alert::query()->where('type', 'temperature')->whereNull('resolved_at')->count());

        $this->assertFalse(Route::has('cold-rooms.index'));
        $this->assertFalse(Route::has('api.v1.sensors.readings'));
        $this->actingAsRole('admin');
        $this->get('/camaras')->assertNotFound();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Cámaras de frío');
        $this->get(route('admin.modules.index'))->assertOk()->assertDontSee('Cámaras frigoríficas');
        $this->get(route('admin.tokens.index'))->assertOk()->assertDontSee('sensor de temperatura');
    }
}
