<?php

namespace Tests\Feature\Catalogs;

use App\Catalogs\CatalogRegistry;
use App\Models\Alert;
use App\Models\Driver;
use App\Models\ImportBatch;
use App\Models\Transporter;
use App\Models\Truck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportExportAllTest extends TestCase
{
    use RefreshDatabase;

    private function import(string $type, string $csv, string $mode): ImportBatch
    {
        $file = UploadedFile::fake()->createWithContent($type.'.csv', $csv);
        $this->post(route('imports.store'), ['type' => $type, 'file' => $file, 'mode' => $mode])->assertRedirect();

        return ImportBatch::query()->latest('id')->firstOrFail();
    }

    public function test_every_main_catalog_can_be_exported_and_imported(): void
    {
        $this->actingAsRole('admin');
        foreach (['clients', 'producers', 'owners', 'providers', 'transporters', 'trucks', 'drivers', 'packers', 'employees', 'crews',
            'destinations', 'varieties', 'sizes', 'grades', 'containers'] as $key) {
            $definition = CatalogRegistry::get($key);
            $this->assertNotSame([], $definition->importColumns(), "{$key} sin columnas de importación");
            $this->get($definition->route('index', ['format' => 'csv']))->assertOk();
            $this->get($definition->route('index'))->assertOk()->assertSee('format=xlsx', false);
            $this->get(route('imports.template', ['type' => $key]))->assertOk();
        }
    }

    public function test_export_then_edit_then_import_updates_existing_records(): void
    {
        $this->actingAsRole('admin');
        $transporter = Transporter::query()->create(['business_name' => 'Fletes del Valle', 'cuit' => '30712345671']);
        Truck::query()->create(['plate' => 'AB123CD', 'brand' => 'Scania', 'transporter_id' => $transporter->id, 'capacity_kg' => 28000]);

        $csv = $this->get(route('catalogs.trucks.index', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('patente', $csv);
        $this->assertStringContainsString('Fletes del Valle', $csv);

        // En Excel se corrige la marca y se agrega un camión nuevo; se vuelve a importar.
        $edited = str_replace('Scania', 'Scania R450', $csv)."\nAC456EF;Fletes del Valle;Semirremolque;Iveco;Stralis;2020;;30000;;;;;;;;Sí\n";
        if (! str_contains($csv, ';')) {
            $edited = str_replace('Scania', 'Scania R450', $csv)."\nAC456EF,Fletes del Valle,Semirremolque,Iveco,Stralis,2020,,30000,,,,,,,,Sí\n";
        }

        // Con «sólo agregar nuevos» el existente se informa como duplicado.
        $create = $this->import('trucks', $edited, 'create');
        $this->assertSame(1, $create->valid_rows);
        $this->assertSame(1, $create->error_rows);

        $batch = $this->import('trucks', $edited, 'upsert');
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(1, $batch->updated_rows);
        $this->post(route('imports.confirm', $batch))->assertSessionHas('success');

        $this->assertSame(2, Truck::query()->count());
        $this->assertSame('Scania R450', Truck::query()->where('plate', 'AB123CD')->value('brand'));
        $new = Truck::query()->where('plate', 'AC456EF')->firstOrFail();
        $this->assertSame($transporter->id, $new->transporter_id);
        $this->assertSame('semi', $new->type);
    }

    public function test_partial_update_only_touches_the_columns_in_the_file(): void
    {
        $this->actingAsRole('admin');
        $driver = Driver::query()->create(['first_name' => 'Carlos', 'last_name' => 'Díaz', 'dni' => '25111222', 'phone' => '381-1', 'license_number' => 'L-9']);

        $batch = $this->import('drivers', "dni,telefono,cuil\n25.111.222,381-999,20-25111222-3\n", 'upsert');
        $this->assertSame(1, $batch->updated_rows);
        $this->post(route('imports.confirm', $batch));

        $driver->refresh();
        $this->assertSame('381-999', $driver->phone);
        $this->assertSame('20251112223', $driver->cuil);
        $this->assertSame('Carlos', $driver->first_name);
        $this->assertSame('L-9', $driver->license_number);
    }

    public function test_unknown_transporter_in_import_is_reported(): void
    {
        $this->actingAsRole('admin');
        $batch = $this->import('trucks', "patente,transportista\nAD789GH,No Existe SA\n", 'create');
        $this->assertSame(0, $batch->valid_rows);
        $this->assertSame(1, $batch->error_rows);
    }

    public function test_truck_document_expirations_raise_alerts(): void
    {
        $this->actingAsRole('admin');
        Truck::query()->create(['plate' => 'AA111BB', 'vtv_expires_on' => today()->addDays(3), 'insurance_expires_on' => today()->subDay()]);
        $this->artisan('galpon:check-alerts')->assertSuccessful();

        $this->assertTrue(Alert::query()->where('title', 'like', '%VTV / RTO por vencer%AA111BB%')->exists());
        $this->assertTrue(Alert::query()->where('title', 'like', '%Seguro vencido%AA111BB%')->where('severity', 'critical')->exists());
    }

    public function test_new_personal_data_fields_are_saved(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('catalogs.drivers.store'), [
            'first_name' => 'Ana', 'last_name' => 'López', 'dni' => '30111222', 'cuil' => '27-30111222-4', 'license_category' => 'E1',
            'emergency_contact' => 'Juan López', 'emergency_phone' => '381-555', 'address' => 'Ruta 9 km 1300', 'active' => 1,
        ])->assertRedirect();
        $this->assertSame('E1', Driver::query()->where('dni', '30111222')->value('license_category'));

        $this->post(route('catalogs.transporters.store'), ['business_name' => 'Trans Norte', 'cbu' => '0110599520000001234567', 'bank_alias' => 'trans.norte', 'active' => 1])->assertRedirect();
        $this->assertSame('0110599520000001234567', Transporter::query()->where('business_name', 'Trans Norte')->value('cbu'));
    }
}
