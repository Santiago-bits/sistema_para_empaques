<?php

namespace Tests\Feature\Crates;

use App\Enums\CrateStatus;
use App\Exceptions\BusinessException;
use App\Models\Crate;
use App\Models\Pallet;
use App\Services\CrateService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** Cada pallet lleva de 1 a 50 cajones: el sistema no deja pasarse; se ve qué ingreso (bines) se usó. */
class PalletCapacityTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_a_pallet_does_not_accept_more_crates_than_its_capacity(): void
    {
        $user = $this->actingAsRole('admin');
        $pallet = $this->pallet();
        $pallet->update(['capacity' => 2]);

        $this->post(route('crates.store'), ['pallet_id' => $pallet->id, 'weight' => 18])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->post(route('crates.store'), ['pallet_id' => $pallet->id, 'weight' => 18])->assertSessionMissing('error');
        $this->post(route('crates.store'), ['pallet_id' => $pallet->id, 'weight' => 18])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está completo (2 de 2 cajones)'));
        $this->assertSame(2, Crate::query()->where('pallet_id', $pallet->id)->count());

        // Un cajón anulado libera su lugar.
        $first = Crate::query()->where('pallet_id', $pallet->id)->firstOrFail();
        $first->forceFill(['status' => CrateStatus::Voided->value])->save();
        app(CrateService::class)->create(['pallet_id' => $pallet->id, 'weight' => 18], $user);
        $this->assertSame(2, $pallet->fresh()->cratesCount());

        // Mover a un pallet lleno un cajón que estaba en otro: tampoco.
        $loose = $this->crate('CJ-SUELTO');
        $this->expectException(BusinessException::class);
        app(CrateService::class)->update($loose, ['pallet_id' => $pallet->id], null);
    }

    public function test_batch_of_labels_stops_at_the_capacity(): void
    {
        $user = $this->actingAsRole('admin');
        $pallet = $this->pallet();
        $pallet->update(['capacity' => 3]);

        try {
            app(CrateService::class)->createBatch(5, ['pallet_id' => $pallet->id], $user);
            $this->fail('Tenía que frenar al llegar a 3');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('3 de 3', $e->getMessage());
        }
        // Todo o nada: no queda ninguno a medias.
        $this->assertSame(0, Crate::query()->where('pallet_id', $pallet->id)->count());
    }

    public function test_capacity_defaults_to_the_setting_and_is_validated_from_1_to_50(): void
    {
        $this->actingAsRole('admin');
        app(SettingsService::class)->set('production.pallet_capacity', 40);
        $pallet = $this->pallet();
        $this->assertSame(40, $pallet->capacityLimit());

        $this->get(route('pallets.create'))->assertOk()->assertSee('Cajones que entran')->assertSee('value="40"', false);
        foreach ([0, 51, 'muchos'] as $bad) {
            $this->post(route('pallets.store'), ['received_at' => now()->format('Y-m-d\TH:i'), 'capacity' => $bad])->assertSessionHasErrors('capacity');
        }
        $this->post(route('pallets.store'), ['received_at' => now()->format('Y-m-d\TH:i'), 'capacity' => 12, 'producer_id' => $this->producer()->id])->assertSessionHasNoErrors();
        $this->assertSame(12, Pallet::query()->latest('id')->first()->capacityLimit());

        $this->post(route('admin.settings.update', 'production'), $this->productionSettings(['pallet_capacity' => 60]))->assertSessionHasErrors('pallet_capacity');
        $this->post(route('admin.settings.update', 'production'), $this->productionSettings(['pallet_capacity' => 30]))->assertSessionHasNoErrors();
        $this->assertSame(30, (int) setting('production.pallet_capacity'));
    }

    public function test_pallet_page_shows_how_full_it_is_and_which_bins_it_came_from(): void
    {
        $this->actingAsRole('admin');
        $lot = $this->lot();
        $lot->update(['bins' => 14]);
        $pallet = $this->pallet();
        $pallet->update(['capacity' => 10, 'lot_id' => $lot->id]);
        $this->crate('CJ-A', CrateStatus::Registered, ['pallet_id' => $pallet->id]);
        $this->crate('CJ-B', CrateStatus::Registered, ['pallet_id' => $pallet->id]);

        $this->get(route('pallets.show', $pallet))->assertOk()
            ->assertSee('2 de 10')->assertSee('Faltan 8')
            ->assertSee('Ingreso de fruta (bines)')->assertSee($lot->code.' · 14 bines')
            ->assertSee('CJ-A')->assertSee('CJ-B');
        $this->get(route('pallets.index'))->assertOk()->assertSee('2 / 10');
        $this->get(route('pallets.create'))->assertOk()->assertSee($lot->code)->assertSee('14 bines');
    }

    private function productionSettings(array $override): array
    {
        return $override + ['weight_min' => '5', 'weight_max' => '30', 'target_daily_kg' => '10000', 'target_weekly_kg' => '60000',
            'target_monthly_kg' => '250000', 'scale_driver' => 'manual'];
    }
}
