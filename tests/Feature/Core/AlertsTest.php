<?php

namespace Tests\Feature\Core;

use App\Models\Alert;
use App\Models\Supply;
use App\Models\User;
use App\Notifications\AlertRaised;
use App\Services\AlertChecker;
use App\Services\AlertService;
use App\Services\ModuleService;
use App\Services\SettingsService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    private function lowSupply(float $stock = 2): Supply
    {
        return Supply::query()->create(['code' => 'CAJA-1', 'name' => 'Caja 18 kg', 'unit' => 'u', 'stock' => $stock, 'min_stock' => 10, 'active' => true]);
    }

    public function test_every_check_runs_without_errors_on_real_data(): void
    {
        $this->seed(DemoSeeder::class);
        foreach (array_unique(AlertChecker::CHECKS) as $module) {
            app(ModuleService::class)->setEnabled($module, true);
        }
        app(SettingsService::class)->set('alerts.enabled', array_fill_keys(array_keys(AlertChecker::CHECKS), true));

        $summary = app(AlertChecker::class)->run();

        foreach ($summary as $type => $row) {
            $this->assertSame('ok', $row['status'], "El chequeo {$type} falló: ".($row['error'] ?? ''));
        }
    }

    public function test_low_stock_raises_one_alert_notifies_and_resolves_itself(): void
    {
        Notification::fake();
        $admin = User::factory()->role('admin')->create();
        $supply = $this->lowSupply();

        app(AlertChecker::class)->run();
        app(AlertChecker::class)->run(); // no duplica

        $alert = Alert::query()->where('type', 'stock_low')->sole();
        $this->assertNull($alert->resolved_at);
        Notification::assertSentToTimes($admin, AlertRaised::class, 1);

        $supply->update(['stock' => 50]);
        app(AlertChecker::class)->run();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_disabled_type_is_not_evaluated(): void
    {
        $this->lowSupply();
        app(SettingsService::class)->set('alerts.enabled', ['stock_low' => false]);
        $this->assertSame('disabled', app(AlertChecker::class)->run()['stock_low']['status']);
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_screen_lists_resolves_and_evaluates(): void
    {
        $this->actingAsRole('admin');
        $alert = app(AlertService::class)->raise('stock_low', 'Stock bajo: Film', 'Quedan 0 rollos', null, 'critical');

        $this->get(route('alerts.index'))->assertOk()->assertSee('Stock bajo: Film')->assertSee('Crítica');
        $this->post(route('alerts.resolve', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->get(route('alerts.index'))->assertDontSee('Stock bajo: Film');
        $this->get(route('alerts.index', ['status' => 'resolved']))->assertSee('Stock bajo: Film');

        $this->post(route('alerts.check'))->assertRedirect()->assertSessionHas('success');
    }

    public function test_operator_can_view_but_not_resolve(): void
    {
        $alert = app(AlertService::class)->raise('stock_low', 'Stock bajo: Film');
        $this->actingAsRole('intake_operator');
        $this->get(route('alerts.index'))->assertOk()->assertDontSee('Resolver');
        $this->post(route('alerts.resolve', $alert))->assertForbidden();
        $this->post(route('alerts.check'))->assertForbidden();

        $this->actingAsRole('packer');
        $this->get(route('alerts.index'))->assertForbidden();
    }
}
