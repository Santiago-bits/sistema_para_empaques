<?php

namespace Tests\Feature\Treasury;

use App\Models\ContainerType;
use App\Models\Crate;
use App\Models\Crew;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Load;
use App\Models\Permission;
use App\Models\Role;
use App\Services\SettingsService;
use Database\Seeders\SystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogsLabelsAndLoadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_catalogs_crud(): void
    {
        $this->actingAsRole('admin');
        $this->assertSame(3, Grade::query()->count()); // Extra, Elegido, Comercial de fábrica

        $this->get(route('catalogs.index'))->assertOk()->assertSee('Selecciones')->assertSee('Tipos de envase')->assertSee('Cuadrillas')->assertSee('Empleados');

        $this->post(route('catalogs.containers.store'), ['code' => 'caj18', 'name' => 'Caja 18 kg', 'kind' => 'box', 'tare_kg' => '0,9', 'capacity_kg' => '18', 'active' => 1])->assertRedirect();
        $this->assertSame('0.90', ContainerType::query()->where('code', 'CAJ18')->value('tare_kg'));

        $this->post(route('catalogs.crews.store'), ['code' => 'CUA1', 'name' => 'Empaque mañana', 'kind' => 'packing', 'active' => 1])->assertRedirect();
        $crew = Crew::query()->firstOrFail();
        $this->post(route('catalogs.employees.store'), [
            'code' => 'emp1', 'first_name' => 'Juan', 'last_name' => 'Pérez', 'dni' => '30.111.222', 'cuil' => '20-30111222-3',
            'crew_id' => $crew->id, 'daily_wage' => '28.000', 'active' => 1,
        ])->assertRedirect();
        $employee = Employee::query()->firstOrFail();
        $this->assertSame('30111222', $employee->dni);
        $this->assertSame('28000.00', $employee->daily_wage);

        // La ficha del empleado lleva a su cuenta corriente.
        $this->get(route('catalogs.employees.show', $employee))->assertOk()->assertSee('Cuenta corriente');
        $this->get(route('accounts.show', ['employee', $employee->id]))->assertOk()->assertSee('Pérez, Juan');
    }

    public function test_staff_permission_alone_can_see_people_catalogs(): void
    {
        $this->actingWithPermissions(['staff.view']);
        $this->get(route('catalogs.index'))->assertOk()->assertSee('Empleados')->assertDontSee('Productores');
        $this->get(route('catalogs.employees.index'))->assertOk();
        $this->get(route('catalogs.employees.create'))->assertForbidden();
    }

    public function test_crate_label_prints_grade_container_and_regulatory_data(): void
    {
        $this->actingAsRole('admin');
        $settings = app(SettingsService::class);
        $settings->set('company.name', 'Empaque La Esperanza');
        $settings->set('label.show_regulatory', true);
        $settings->set('label.senasa_number', '1234');
        $settings->set('label.renspa', '13.012.0.00456/00');
        $settings->set('label.provincial_registry', '0456');
        $settings->set('label.nominal_kg', 18);

        $box = ContainerType::query()->create(['code' => 'CAJ', 'name' => 'Caja de cartón', 'kind' => 'box']);
        $crate = Crate::query()->create(['code' => 'CJ-500', 'status' => 'registered', 'grade_id' => Grade::query()->where('code', 'EXT')->value('id'), 'container_type_id' => $box->id]);

        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()
            ->assertSee('Empaque La Esperanza')
            ->assertSee('SENASA E-1234')
            ->assertSee('RENSPA 13.012.0.00456/00')
            ->assertSee('Reg. Prov. Empaque N° 0456')
            ->assertSee('Decreto-Ley 9.244/63')
            ->assertSee('Producción Argentina')
            ->assertSee('Extra')
            ->assertSee('Caja de cartón')
            ->assertSee('aprox. 18 kg');

        $settings->set('label.show_regulatory', false);
        $this->get(route('labels.crates', ['ids' => [$crate->id]]))->assertOk()->assertDontSee('SENASA');
    }

    public function test_label_and_treasury_settings_are_saved(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('admin.settings.index', ['tab' => 'label']))->assertOk()->assertSee('Inscripción SENASA');
        $this->get(route('admin.settings.index', ['tab' => 'treasury']))->assertOk()->assertSee('Tasa de asociación');

        $this->post(route('admin.settings.update', 'label'), ['senasa_number' => 'E-77', 'show_regulatory' => 1, 'nominal_kg' => '18,5'])->assertSessionHas('success');
        $this->assertSame('E-77', setting('label.senasa_number'));
        $this->assertSame(18.5, (float) setting('label.nominal_kg'));

        $this->post(route('admin.settings.update', 'treasury'), ['association_fee_per_kg' => '2,75', 'check_warning_days' => 10])->assertSessionHas('success');
        $this->assertSame(2.75, (float) setting('treasury.association_fee_per_kg'));
        $this->assertFalse((bool) setting('treasury.post_test_invoices'));
    }

    public function test_load_commercial_fields(): void
    {
        $this->actingAsRole('loads_operator');
        $this->post(route('loads.store'), [
            'date' => today()->toDateString(), 'trailer_plate' => 'ab 123 cd', 'guide_number' => 'DTV-1',
            'commercial_destination' => 'export', 'sales_channel' => 'export', 'sale_condition' => 'consignment', 'freight_amount' => '350.000',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $load = Load::query()->firstOrFail();
        $this->assertSame('AB123CD', $load->trailer_plate);
        $this->assertSame('350000.00', $load->freight_amount);
        $this->get(route('loads.show', $load))->assertOk()->assertSee('Consignación')->assertSee('DTV-1');

        $this->post(route('loads.store'), ['date' => today()->toDateString(), 'trailer_plate' => 'XX', 'sale_condition' => 'gratis'])
            ->assertSessionHasErrors(['trailer_plate', 'sale_condition']);
    }

    public function test_upgrade_grants_new_permissions_to_existing_system_roles(): void
    {
        $role = Role::query()->where('slug', 'billing')->firstOrFail();
        $slug = 'cash.manage';
        // Simula una instalación anterior al módulo: el permiso no existía.
        Permission::query()->where('slug', $slug)->delete();
        $this->assertFalse($role->permissions()->where('slug', $slug)->exists());

        $this->seed(SystemSeeder::class);

        $this->assertTrue($role->fresh()->permissions()->where('slug', $slug)->exists());
        // Un rol que no lo tendría por defecto no lo recibe.
        $this->assertFalse(Role::query()->where('slug', 'packer')->firstOrFail()->permissions()->where('slug', $slug)->exists());
    }
}
