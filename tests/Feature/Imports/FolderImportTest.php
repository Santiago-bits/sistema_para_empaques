<?php

namespace Tests\Feature\Imports;

use App\Models\Driver;
use App\Models\ImportBatch;
use App\Models\Owner;
use App\Models\Producer;
use App\Models\Transporter;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Importar una carpeta: cada planilla va sola a su sección y, ante la duda, no se elige nada. */
class FolderImportTest extends TestCase
{
    use RefreshDatabase;

    private string $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->group = (string) Str::uuid();
    }

    private function csv(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function xlsx(string $name, array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('imports.folder.upload'), ['group' => $this->group, 'mode' => 'upsert', 'file' => $file], ['Accept' => 'application/json']);
    }

    private function batch(string $filename): ImportBatch
    {
        return ImportBatch::query()->where('group_uuid', $this->group)->where('filename', $filename)->firstOrFail();
    }

    public function test_each_spreadsheet_goes_to_its_own_section_and_nothing_is_saved_until_confirmed(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('imports.folder.create'))->assertOk()->assertSee('Elegir una carpeta')->assertSee('Carpeta comprimida (.zip)');

        // Camiones antes que su transportista (el orden en que el navegador manda la carpeta no importa).
        $this->upload($this->xlsx('camiones.xlsx', [
            ['patente', 'transportista', 'marca'],
            ['AB123CD', 'Transportes del Sur SA', 'Scania'],
        ]))->assertOk()->assertJson(['files' => [['recognized' => true, 'section' => 'Camiones']]]);
        $this->upload($this->csv('transportistas.csv', "razon_social;cuit;telefono\nTransportes del Sur SA;30500001735;2995551111\n"))
            ->assertOk()->assertJson(['files' => [['recognized' => true, 'section' => 'Transportistas']]]);
        $this->upload($this->xlsx('Choferes 2026.xlsx', [
            ['Nombre', 'Apellido', 'DNI', 'Licencia número', 'Transportista'],
            ['Juan', 'Pérez', '30111222', 'L-555', 'Transportes del Sur SA'],
        ]))->assertOk()->assertJson(['files' => [['recognized' => true, 'section' => 'Camioneros']]]);
        $this->upload($this->csv('productores.csv', "codigo;nombre;cuit;renspa\nPR-1;Quinta Albiero;20123456786;12.345.6.78901/00\n"))
            ->assertOk()->assertJson(['files' => [['recognized' => true, 'section' => 'Productores']]]);

        $this->assertSame('drivers', $this->batch('Choferes 2026.xlsx')->type);
        // Nada se guardó todavía.
        $this->assertSame(0, Transporter::query()->count() + Truck::query()->count() + Driver::query()->count() + Producer::query()->count());

        $this->get(route('imports.folder.show', $this->group))->assertOk()
            ->assertSee('camiones.xlsx')->assertSee('Confirmar e importar');

        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect()->assertSessionHas('success');

        // Cada dato en su sección, sin mezclarse, y el camión y el camionero enganchados a su transportista.
        $transporter = Transporter::query()->sole();
        $this->assertSame('Transportes del Sur SA', $transporter->business_name);
        $this->assertSame($transporter->id, Truck::query()->sole()->transporter_id);
        $this->assertSame($transporter->id, Driver::query()->sole()->transporter_id);
        $this->assertSame('Quinta Albiero', Producer::query()->sole()->name);
        $this->assertSame(0, Owner::query()->count());
        $this->assertSame(4, ImportBatch::query()->where('group_uuid', $this->group)->where('status', 'imported')->count());
    }

    public function test_when_columns_fit_several_sections_it_does_not_guess_and_the_user_chooses(): void
    {
        $this->actingAsRole('admin');

        // Productores y propietarios tienen las mismas columnas: sin pista en el nombre, no se elige.
        $this->upload($this->csv('datos.csv', "codigo;nombre;cuit;telefono\nX-1;Los Álamos;20123456786;2995550000\n"))
            ->assertOk()->assertJson(['files' => [['recognized' => false]]]);
        $batch = $this->batch('datos.csv');
        $this->assertSame('unrecognized', $batch->status);
        $this->assertStringContainsString('varias secciones', $batch->detection['reason']);

        // Confirmar la carpeta no carga lo que no está reconocido.
        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect();
        $this->assertSame(0, Producer::query()->count() + Owner::query()->count());

        // El usuario elige: va a propietarios, y sólo ahí.
        $this->post(route('imports.retype', $batch), ['type' => 'owners'])->assertRedirect(route('imports.folder.show', $this->group));
        $this->assertSame('validated', $batch->refresh()->status);
        $this->assertSame('user', $batch->detection['by']);
        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect();
        $this->assertSame('Los Álamos', Owner::query()->sole()->name);
        $this->assertSame(0, Producer::query()->count());
    }

    public function test_name_and_columns_that_disagree_are_never_mixed(): void
    {
        $this->actingAsRole('admin');

        // Dice «productores» pero trae camioneros: queda sin reconocer.
        $this->upload($this->csv('productores.csv', "nombre;apellido;dni;licencia_numero\nJuan;Pérez;30111222;L-1\n"))
            ->assertOk()->assertJson(['files' => [['recognized' => false]]]);
        $this->assertStringContainsString('Camioneros', $this->batch('productores.csv')->detection['reason']);

        // Columnas que no son de ninguna sección.
        $this->upload($this->csv('cosas.csv', "fruta_favorita;color\nnaranja;rojo\n"))->assertOk()->assertJson(['files' => [['recognized' => false]]]);

        // Elegir a mano una sección cuyas columnas no corresponden tampoco carga nada.
        $this->post(route('imports.retype', $this->batch('productores.csv')), ['type' => 'producers'])->assertRedirect();
        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect();
        $this->assertSame(0, Producer::query()->count() + Driver::query()->count());
    }

    public function test_a_zipped_folder_works_like_a_folder(): void
    {
        $this->actingAsRole('admin');
        $path = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('Planillas/transportistas.csv', "razon_social;cuit\nTransportes del Sur SA;30500001735\n");
        $zip->addFromString('Planillas/camioneros.csv', "nombre;apellido;dni;transportista\nJuan;Pérez;30111222;Transportes del Sur SA\n");
        $zip->addFromString('Planillas/~$camioneros.csv', 'temporal de Excel');
        $zip->addFromString('__MACOSX/Planillas/._camioneros.csv', 'basura de Mac');
        $zip->addFromString('Planillas/notas.docx', 'no es planilla');
        $zip->close();

        $this->upload(new UploadedFile($path, 'Planillas.zip', 'application/zip', null, true))->assertOk()
            ->assertJsonCount(2, 'files')
            ->assertJson(['files' => [['filename' => 'transportistas.csv', 'section' => 'Transportistas'], ['filename' => 'camioneros.csv', 'section' => 'Camioneros']]]);

        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect();
        $this->assertSame(Transporter::query()->sole()->id, Driver::query()->sole()->transporter_id);

        // Un .zip sin planillas avisa en vez de no hacer nada.
        $empty = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($empty, \ZipArchive::CREATE);
        $zip->addFromString('foto.jpg', 'x');
        $zip->close();
        $this->upload(new UploadedFile($empty, 'fotos.zip', 'application/zip', null, true))->assertUnprocessable()->assertJsonFragment(['message' => 'El .zip no tiene planillas (.xlsx o .csv).']);
    }

    public function test_spreadsheets_that_depend_on_others_of_the_same_folder_preview_and_import_correctly(): void
    {
        $this->actingAsRole('admin');
        $clients = [['razon_social', 'nombre_fantasia', 'cuit', 'condicion_iva']];
        $destinations = [['nombre', 'cliente', 'direccion', 'localidad']];
        $transporters = [['razon_social', 'cuit', 'condicion_iva', 'contacto']];
        $trucks = [['patente', 'transportista', 'tipo', 'marca']];
        $drivers = [['nombre', 'apellido', 'dni', 'cuil', 'transportista', 'licencia_vencimiento']];
        $crews = [['codigo', 'nombre', 'tipo', 'encargado']];
        $employees = [['legajo', 'nombre', 'apellido', 'dni', 'cuadrilla']];
        $cuits = ['30500001018', '30500001026', '30500001034', '30500001042', '30500001050'];
        foreach ($cuits as $i => $cuit) {
            $n = $i + 1;
            $clients[] = ["Frutas del Valle {$n} S.A.", "Mercado {$n}", $cuit, 'RI'];
            // El destino nombra al cliente por su nombre de fantasía.
            $destinations[] = ["Depósito {$n}", "Mercado {$n}", "Calle {$n}", 'Neuquén'];
            $transporters[] = ["Transportes {$n} SRL", $cuit, 'RI', "Contacto {$n}"];
            $trucks[] = [sprintf('AB%03dCD', $n), "Transportes {$n} SRL", 'Semirremolque', 'Scania'];
            $drivers[] = ["Chofer{$n}", 'Pérez', (string) (30000000 + $n), '20'.(30000000 + $n).'1', $cuit, '31/12/2027'];
            $crews[] = ["CU-{$n}", "Cuadrilla {$n}", 'cosecha', 'Encargado'];
            $employees[] = ["L-{$n}", "Empleado{$n}", 'Gómez', (string) (31000000 + $n), "Cuadrilla {$n}"];
        }
        $files = ['01_clientes.xlsx' => $clients, '02_destinos.xlsx' => $destinations, '05_transportistas.xlsx' => $transporters,
            '06_camiones.xlsx' => $trucks, '07_choferes.xlsx' => $drivers, '08_cuadrillas.xlsx' => $crews, '09_empleados.xlsx' => $employees];
        // El orden en que el navegador manda los archivos no importa: los que dependen, primero.
        foreach (array_reverse($files, true) as $name => $rows) {
            $this->upload($this->xlsx($name, $rows))->assertOk()->assertJson(['files' => [['recognized' => true]]]);
        }

        // Vista previa realista: todas las filas correctas, aunque sus clientes/transportistas/cuadrillas todavía no estén cargados.
        $this->get(route('imports.folder.show', $this->group))->assertOk();
        foreach (array_keys($files) as $name) {
            $batch = $this->batch($name);
            $this->assertSame([5, 0], [$batch->valid_rows, $batch->error_rows], $name.': '.json_encode($batch->errors, JSON_UNESCAPED_UNICODE));
        }
        // La vista previa no guardó nada.
        $this->assertSame(0, \App\Models\Client::query()->count() + Transporter::query()->count() + \App\Models\Crew::query()->count());

        $this->post(route('imports.folder.confirm', $this->group))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(5, \App\Models\Destination::query()->whereNotNull('client_id')->count());
        $this->assertSame(5, Truck::query()->whereNotNull('transporter_id')->count());
        $this->assertSame(5, Driver::query()->whereNotNull('transporter_id')->count());
        $this->assertSame(5, \App\Models\Employee::query()->whereNotNull('crew_id')->count());
        $this->assertSame('Mercado 3', \App\Models\Destination::query()->where('name', 'Depósito 3')->sole()->client->name);
    }

    public function test_a_reference_that_does_not_exist_says_which_one_and_what_to_do(): void
    {
        $this->actingAsRole('admin');
        $this->upload($this->csv('destinos.csv', "nombre;cliente;localidad\nDepósito Norte;Cliente Que No Existe;Neuquén\n"))->assertOk();
        $this->get(route('imports.folder.show', $this->group))->assertOk();
        $error = $this->batch('destinos.csv')->errors[0];
        $this->assertSame('cliente', $error['field']);
        $this->assertStringContainsString('No se encontró «Cliente Que No Existe»', $error['message']);
        $this->assertStringContainsString('misma carpeta', $error['message']);
    }

    public function test_dtv_spreadsheets_are_sent_to_their_own_screen(): void
    {
        $this->actingAsRole('admin');
        $this->upload($this->xlsx('planilla.xlsx', [
            ['', '', 'REGISTRO DE DTV-e EMPAQUE'],
            ['FECHA', 'E / I', 'N° DTV-e', 'ESPECIE'],
        ]))->assertOk()->assertJson(['files' => [['recognized' => false]]]);
        $this->assertSame('dtv', $this->batch('planilla.xlsx')->detection['special']);
        $this->get(route('imports.folder.show', $this->group))->assertOk()->assertSee('Ir a DTV-e');
    }

    public function test_discard_all_leaves_the_database_untouched(): void
    {
        $this->actingAsRole('admin');
        $this->upload($this->csv('transportistas.csv', "razon_social;cuit\nTransportes del Sur SA;30500001735\n"))->assertOk();
        $this->upload($this->csv('datos.csv', "codigo;nombre\nX;Y\n"))->assertOk();

        $this->post(route('imports.folder.discard', $this->group))->assertRedirect(route('imports.index'));
        $this->assertSame(0, Transporter::query()->count());
        $this->assertSame(2, ImportBatch::query()->where('group_uuid', $this->group)->where('status', 'discarded')->count());
        Storage::disk('local')->assertDirectoryEmpty('imports');
    }

    public function test_permissions_and_files(): void
    {
        $this->actingWithPermissions(['catalogs.view']);
        $this->get(route('imports.folder.create'))->assertForbidden();
        $this->upload($this->csv('productores.csv', "codigo;nombre\nA;B\n"))->assertForbidden();

        $this->actingAsRole('admin');
        $this->upload(UploadedFile::fake()->create('virus.exe', 10))->assertUnprocessable();

        // Una carpeta de otro usuario no se puede completar con archivos propios.
        ImportBatch::query()->create(['type' => '', 'status' => 'unrecognized', 'filename' => 'x.csv', 'path' => 'imports/x.csv',
            'group_uuid' => $this->group, 'user_id' => User::factory()->create()->id]);
        $this->upload($this->csv('productores.csv', "codigo;nombre\nA;B\n"))->assertForbidden();
    }
}
