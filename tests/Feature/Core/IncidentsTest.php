<?php

namespace Tests\Feature\Core;

use App\Models\Alert;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

class IncidentsTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'missing_crate', 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'), 'area' => 'Línea 1',
            'description' => 'Falta un cajón del pallet', 'priority' => 'medium',
        ], $overrides);
    }

    public function test_register_link_and_follow_incident(): void
    {
        $user = $this->actingAsRole('supervisor');
        $crate = $this->crate('CJ-000555');
        $this->get(route('incidents.index'))->assertOk();
        $this->get(route('incidents.create'))->assertOk();

        $this->post(route('incidents.store'), $this->payload(['related_type' => 'crate', 'related_code' => 'cj-000555', 'responsible_id' => $user->id]))
            ->assertRedirect();
        $incident = Incident::query()->sole();
        $this->assertStringStartsWith('INC-', $incident->number);
        $this->assertTrue($incident->related->is($crate));
        $this->get(route('incidents.show', $incident))->assertOk()->assertSee('Cajón CJ-000555')->assertSee('Abierto');

        $this->post(route('incidents.status', $incident), ['status' => 'resolved'])->assertSessionHas('error'); // falta resolución
        $this->post(route('incidents.status', $incident), ['status' => 'in_progress'])->assertSessionHas('success');
        $this->post(route('incidents.status', $incident), ['status' => 'resolved', 'resolution' => 'Apareció en la cámara 2'])->assertSessionHas('success');

        $incident->refresh();
        $this->assertSame('resolved', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(['open', 'in_progress', 'resolved'], $incident->stateHistories->pluck('to_state')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'status_change', 'auditable_type' => 'incident']);
    }

    public function test_invalid_transition_and_concurrent_change(): void
    {
        $user = $this->actingAsRole('supervisor');
        $service = app(IncidentService::class);
        $incident = $service->create($this->payload(), $user);

        $service->changeStatus($incident, 'closed', $user, 'Duplicado');
        $this->post(route('incidents.status', $incident), ['status' => 'resolved', 'resolution' => 'x'])->assertSessionHas('error');

        // Dos usuarios con la misma versión en pantalla: sólo gana el primero.
        $stale = Incident::query()->find($incident->id);
        $service->changeStatus($incident->fresh(), 'in_progress', $user);
        $this->expectException(\App\Exceptions\BusinessException::class);
        $service->changeStatus($stale, 'in_progress', $user);
    }

    public function test_high_priority_raises_alert_resolved_with_incident(): void
    {
        $user = $this->actingAsRole('supervisor');
        $this->post(route('incidents.store'), $this->payload(['priority' => 'critical', 'type' => 'transport']));
        $incident = Incident::query()->sole();
        $alert = Alert::query()->where('type', 'incident')->sole();
        $this->assertSame('critical', $alert->severity);

        app(IncidentService::class)->changeStatus($incident, 'resolved', $user, 'Llegó el camión de reemplazo');
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_validation_unknown_related_code_and_permissions(): void
    {
        $this->actingAsRole('supervisor');
        $this->post(route('incidents.store'), $this->payload(['related_type' => 'load', 'related_code' => 'CARG-99999']))->assertSessionHas('error');
        $this->post(route('incidents.store'), ['type' => 'x', 'priority' => 'urgente', 'description' => ''])
            ->assertSessionHasErrors(['type', 'priority', 'description', 'occurred_at']);
        $this->assertSame(0, Incident::query()->count());

        $this->actingAsRole('packer');
        $this->get(route('incidents.index'))->assertForbidden();
    }
}
