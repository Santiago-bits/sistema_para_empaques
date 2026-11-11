<?php

namespace Tests\Feature\Crates;

use App\Models\ContainerType;
use App\Models\Crate;
use App\Services\CrateService;
use App\Services\LabelService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** Etiqueta legible y a prueba de cambios: peso grande en un recuadro de seguridad con código de control. */
class LabelSecurityTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_new_crates_come_in_wooden_crates_by_default(): void
    {
        $user = $this->actingAsRole('admin');
        $wood = ContainerType::query()->where('name', 'Cajón de madera')->first();
        $this->assertNotNull($wood, 'El galpón trae «Cajón de madera» de fábrica');
        $this->assertSame($wood->id, ContainerType::defaultForCrates());

        $crate = app(CrateService::class)->create(['weight' => 18], $user);
        $this->assertSame($wood->id, $crate->container_type_id);

        // Se puede elegir otro como predeterminado.
        $box = ContainerType::query()->create(['code' => 'CART', 'name' => 'Caja de cartón', 'kind' => 'box']);
        app(SettingsService::class)->set('production.default_container_type_id', $box->id);
        $this->assertSame($box->id, app(CrateService::class)->create([], $user)->container_type_id);
        $this->get(route('labels.crates'))->assertOk()->assertSee('Envase');
    }

    public function test_label_has_big_weight_security_box_and_control_code(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-000777', 'registered', ['weight' => 18.47]);
        $control = app(LabelService::class)->controlCode($crate);
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{4}$/', $control);

        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('class="secure"', false)->assertSee('Peso neto')->assertSee('18,47 kg')
            ->assertSee('CTRL '.$control)->assertSee('preserveAspectRatio="none"', false)
            ->assertSee('class="barcode-text"', false);

        // Si cambia el peso, cambia el control: una etiqueta vieja o retocada a mano ya no coincide.
        $crate->forceFill(['weight' => 19.47])->save();
        $this->assertNotSame($control, app(LabelService::class)->controlCode($crate->fresh()));

        // La ficha del cajón, la del pallet y «Seguir un cajón» muestran con qué comparar.
        $fresh = app(LabelService::class)->controlCode($crate->fresh());
        $this->get(route('crates.show', $crate))->assertOk()->assertSee('Verificar etiqueta')->assertSee($fresh)->assertSee('19,47 kg');
        $this->get(route('traceability.index', ['code' => 'CJ-000777']))->assertOk()->assertSee('Verificar etiqueta')->assertSee($fresh);
        $pallet = $this->pallet();
        $this->get(route('pallets.show', $pallet))->assertOk()->assertSee('Verificar etiqueta')->assertSee(app(LabelService::class)->controlCode($pallet));
    }

    public function test_labels_too_small_to_read_are_not_allowed(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-000001');
        $this->get(route('labels.crates', ['ids' => [$crate->id], 'width' => 40, 'height' => 25]))->assertSessionHasErrors(['width', 'height']);
        $this->get(route('labels.crates', ['ids' => [$crate->id], 'width' => 50, 'height' => 30]))->assertOk()->assertSee('compact');
    }

    public function test_legal_legends_print_by_default_and_missing_numbers_are_warned(): void
    {
        $this->actingAsRole('admin');
        $crate = $this->crate('CJ-000001');

        // De fábrica salen el origen y el decreto; y se avisa qué números faltan cargar.
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('class="regulatory"', false)->assertSee('Producción Argentina')->assertSee('Decreto-Ley 9.244/63');
        $this->get(route('labels.crates'))->assertOk()
            ->assertSee('Faltan datos legales para la etiqueta')->assertSee('SENASA, Registro provincial, RENSPA')
            ->assertSee(route('admin.settings.index', ['tab' => 'label']), false);

        $settings = app(SettingsService::class);
        $settings->set('label.senasa_number', '1234');
        $settings->set('label.provincial_registry', '0456');
        $settings->set('label.renspa', '13.012.0.00456/00');
        $this->get(route('labels.crates'))->assertOk()->assertDontSee('Faltan datos legales');
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('SENASA E-1234')->assertSee('Reg. Prov. Empaque N° 0456')->assertSee('RENSPA 13.012.0.00456/00');

        // Si se apagan, se avisa que no se están imprimiendo.
        $settings->set('label.show_regulatory', false);
        $this->get(route('labels.crates'))->assertOk()->assertSee('Los datos legales no se están imprimiendo');
    }
}
