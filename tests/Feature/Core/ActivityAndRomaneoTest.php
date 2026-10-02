<?php

namespace Tests\Feature\Core;

use App\Models\Lot;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityAndRomaneoTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_staff_activity(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('username', 'admin.demo')->firstOrFail();
        $this->actingAs($admin);

        foreach ([7, 30, 90] as $days) {
            $this->get(route('admin.activity.index', ['days' => $days]))->assertOk()->assertSee('Actividad del personal')->assertSee($admin->full_name);
        }
        $this->get(route('admin.activity.index', ['days' => 5]))->assertSessionHasErrors('days');

        $this->actingAsRole('packer');
        $this->get(route('admin.activity.index'))->assertForbidden();
    }

    public function test_lot_romaneo_shows_packed_kilos_yield_and_waste(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAsRole('admin');
        $lot = Lot::query()->whereNotNull('kg_received')->orderBy('id')->firstOrFail();

        $this->get(route('lots.show', $lot))->assertOk()->assertSee(route('lots.romaneo', $lot), false);
        $this->get(route('lots.romaneo', $lot))->assertOk()
            ->assertSee('ROMANEO')->assertSee($lot->code)->assertSee($lot->producer->name)
            ->assertSee('Rinde de empaque')->assertSee('Firma del productor');

        $this->actingAsRole('packer');
        $this->get(route('lots.romaneo', $lot))->assertForbidden();
    }
}
