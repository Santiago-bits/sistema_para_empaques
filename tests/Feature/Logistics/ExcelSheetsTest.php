<?php

namespace Tests\Feature\Logistics;

use App\Models\Client;
use App\Models\Driver;
use App\Models\DtvDocument;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\SupplyYield;
use App\Models\Treatment;
use App\Models\Variety;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Lo que el galpón llevaba en el Excel «LA CALANDRIA»: DTV-e, ingresos, rendimiento por quinta, tratamientos,
 * rendimiento de cera y planilla de cargas con precio por unidad.
 */
class ExcelSheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dtv_register_with_lines_balance_correction_and_export(): void
    {
        $this->actingAsRole('admin');
        $lane = Variety::query()->create(['code' => 'LANE', 'name' => 'Lane late', 'species' => 'Naranja']);

        $this->get(route('dtv.create', ['direction' => 'out']))->assertOk()->assertSee('Nuevo DTV-e de egreso');
        $this->post(route('dtv.store'), [
            'date' => '2026-10-02', 'direction' => 'out', 'number' => '14606819-7', 'doc_type' => 'EMP-CTC', 'issuer' => 'Stivanello Orestes R.',
            'establishment' => 'E-2929-b-C', 'recipient' => 'Bromex S.A.', 'destination' => 'Guaymallén zona seg.', 'transport' => 'Lima Gustavo',
            'lines' => [
                ['variety_id' => $lane->id, 'quantity' => '54', 'unit' => 'Cajón', 'kg_per_unit' => '18'],
                ['variety_name' => 'Murcott', 'species' => 'Mandarina', 'quantity' => '108', 'unit' => 'Cajón', 'kg_per_unit' => '18'],
                ['quantity' => ''], // línea vacía: se ignora
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $doc = DtvDocument::query()->with('lines')->firstOrFail();
        $this->assertCount(2, $doc->lines);
        $this->assertSame('972.00', $doc->lines[0]->kg_total);
        $this->assertSame('Naranja', $doc->lines[0]->species);
        $this->assertSame(-2916.0, $doc->signedKg());

        // Un ingreso para ver el saldo.
        $this->post(route('dtv.store'), ['date' => '2026-10-01', 'direction' => 'in', 'number' => 'DTV 1/10',
            'lines' => [['variety_id' => $lane->id, 'quantity' => '40', 'unit' => 'Bin', 'kg_total' => '16000']]])->assertSessionHasNoErrors();
        $this->get(route('dtv.index'))->assertOk()->assertSee('14606819-7')->assertSee('Bromex S.A.')->assertSee('Saldo de kilos por variedad')
            ->assertSee('15.028,00'); // 16000 − 972 de Lane late

        // Mismo número en el mismo sentido: no se repite.
        $this->post(route('dtv.store'), ['date' => '2026-10-02', 'direction' => 'out', 'number' => '14606819-7', 'lines' => [['quantity' => '1']]])
            ->assertSessionHasErrors('number');

        // Corregir pide motivo.
        $this->put(route('dtv.update', $doc), ['date' => '2026-10-02', 'direction' => 'out', 'number' => '14606819-7', 'lines' => [['variety_id' => $lane->id, 'quantity' => '60', 'kg_per_unit' => '18']]])
            ->assertSessionHasErrors('reason');
        $this->put(route('dtv.update', $doc), ['date' => '2026-10-02', 'direction' => 'out', 'number' => '14606819-7', 'reason' => 'Eran 60 cajones',
            'lines' => [['variety_id' => $lane->id, 'quantity' => '60', 'kg_per_unit' => '18']]])->assertSessionHasNoErrors();
        $this->assertSame(1080.0, (float) $doc->fresh()->lines()->sum('kg_total'));

        $this->get(route('dtv.index', ['format' => 'xlsx']))->assertOk()->assertHeader('content-disposition');

        $this->post(route('dtv.destroy', $doc), ['reason' => 'Cargado por error'])->assertRedirect(route('dtv.index'));
        $this->assertSoftDeleted($doc);
    }

    public function test_imports_the_dtv_sheet_as_it_is_in_the_shed_excel(): void
    {
        $this->actingAsRole('admin');
        Variety::query()->create(['code' => 'VAL', 'name' => 'Valencia Late', 'species' => 'Naranja']);

        $path = tempnam(sys_get_temp_dir(), 'dtv').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['', '', '', '', '', '', '', 'REGISTRO DE DTV-e EMPAQUE LA CALANDRIA']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['FECHA', 'E / I', 'N° DTV-e', 'TIPO', 'EMISOR', 'ESTABLECIMIENTO', 'DESTINATARIO', 'DESTINO', 'ESPECIE', 'VARIEDAD', 'CANT.', 'UNIDAD', 'KG', 'KG TOTALES', 'TRANSPORTE', 'OBSERVACIONES']));
        $writer->addRow(Row::fromValues(['02/10/2026', 'Egresos', '14606819-7', 'EMP-CTC', 'Stivanello Orestes R.', 'E-2929-b-C', 'Bromex S.A.', 'Guaymallen zona seg.', 'Naranja', 'Lane late', '54.00', 'Cajon', '18.00', '-972.00', 'Lima Gustavo', '']));
        $writer->addRow(Row::fromValues(['02/10/2026', 'Egresos', '14606819-7', 'EMP-CTC', 'Stivanello Orestes R.', 'E-2929-b-C', 'Bromex S.A.', 'Guaymallen zona seg.', 'Naranja', 'Valencia Late', '162.00', 'Cajon', '18.00', '-2916.00', 'Lima Gustavo', '']));
        $writer->addRow(Row::fromValues(['01/10/2026', 'Ingresos', 'DTV 1/10', 'PROD-EMP', 'Quinta Albiero', '', 'La Calandria', '', 'Mandarina', 'Murcott', '40', 'Bin', '', '16000', 'Comparin Federico', '']));
        $writer->addRow(Row::fromValues(['', 'Egresos', '', '', '', '', '', '', '', '', '10', '', '', '', '', ''])); // sin fecha ni número
        $writer->close();

        $upload = new UploadedFile($path, 'LA CALANDRIA.xlsx', null, null, true);
        $this->post(route('dtv.import'), ['file' => $upload])->assertRedirect(route('dtv.index'))->assertSessionHas('success');

        $this->assertSame(2, DtvDocument::query()->count());
        $out = DtvDocument::query()->where('number', '14606819-7')->with('lines.variety')->firstOrFail();
        $this->assertSame('out', $out->direction);
        $this->assertCount(2, $out->lines);
        $this->assertSame('Valencia Late', $out->lines[1]->variety?->name); // se vinculó con la variedad cargada
        $this->assertSame('3888.00', number_format((float) $out->lines->sum('kg_total'), 2, '.', ''));
        $this->assertSame('in', DtvDocument::query()->where('number', 'DTV 1/10')->value('direction'));

        // Volver a subir la misma planilla no duplica.
        $again = new UploadedFile($path, 'LA CALANDRIA.xlsx', null, null, true);
        $this->post(route('dtv.import'), ['file' => $again])->assertSessionHas('success');
        $this->assertSame(2, DtvDocument::query()->count());
        @unlink($path);
    }

    public function test_dtv_drafts_from_a_lot_and_permissions(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Quinta Albiero']);
        $variety = Variety::query()->create(['code' => 'MUR', 'name' => 'Murcott', 'species' => 'Mandarina']);
        $driver = Driver::query()->create(['first_name' => 'Federico', 'last_name' => 'Comparin', 'dni' => '30111222']);
        $this->post(route('lots.store'), ['date' => '2026-09-30', 'producer_id' => $producer->id, 'variety_id' => $variety->id,
            'driver_id' => $driver->id, 'bins' => 30, 'dtv_number' => 'DTV 1/10', 'kg_received' => '12.000'])->assertSessionHasNoErrors();
        $lot = Lot::query()->firstOrFail();
        $this->assertSame(30, $lot->bins);

        $this->get(route('lots.index'))->assertOk()->assertSee('Comparin')->assertSee('DTV 1/10')->assertSee('Quinta Albiero');
        $this->get(route('lots.index', ['format' => 'xlsx']))->assertOk()->assertHeader('content-disposition');
        $this->get(route('dtv.create', ['lot' => $lot->id]))->assertOk()->assertSee('DTV 1/10')->assertSee('Quinta Albiero');

        $this->actingWithPermissions(['loads.view']);
        $this->get(route('dtv.index'))->assertForbidden();
        $this->get(route('treatments.index'))->assertForbidden();
    }

    public function test_treatments_with_configurable_types_and_totals(): void
    {
        $this->actingAsRole('admin');
        app(SettingsService::class)->set('treatments.types', ['Frío', 'Bromuro']);
        $client = Client::query()->create(['business_name' => 'Gualdesi', 'cuit' => '30712345679', 'tax_condition' => 'RI']);

        $this->get(route('treatments.create'))->assertOk()->assertSee('Frío')->assertSee('Bromuro');
        foreach ([['Neuquén', '108', 'Frío'], ['Puerto Madryn', '540', 'Bromuro'], ['Neuquén', '54', 'Bromuro']] as [$destination, $qty, $type]) {
            $this->post(route('treatments.store'), ['date' => '2026-09-22', 'client_id' => $client->id, 'destination' => $destination,
                'quantity' => $qty, 'unit' => 'Cajón', 'type' => $type, 'provider' => 'Bromex'])->assertSessionHasNoErrors();
        }
        $this->get(route('treatments.index'))->assertOk()->assertSee('Puerto Madryn')->assertSee('594'); // 540 + 54 de bromuro
        $this->get(route('treatments.index', ['format' => 'xlsx']))->assertOk();

        $t = Treatment::query()->firstOrFail();
        $this->put(route('treatments.update', $t), ['date' => '2026-09-22', 'quantity' => '110', 'unit' => 'Cajón', 'type' => 'Frío'])->assertSessionHasErrors('reason');
        $this->put(route('treatments.update', $t), ['date' => '2026-09-22', 'quantity' => '110', 'unit' => 'Cajón', 'type' => 'Frío', 'reason' => 'Eran 110'])->assertSessionHasNoErrors();
        $this->assertSame('110.00', $t->fresh()->quantity);

        $this->post(route('admin.settings.update', 'treatments'), ['types' => ['Tratamiento en frío', '', 'Bromuro de metilo']])->assertSessionHas('success');
        $this->assertSame(['Tratamiento en frío', 'Bromuro de metilo'], Treatment::types());
    }

    public function test_wax_yield_counts_packed_boxes_between_dates(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('yields.store'), ['name' => 'Tambor de cera N° 1', 'started_on' => today()->subDays(5)->toDateString(), 'quantity_used' => '200', 'unit' => 'litros'])
            ->assertSessionHasNoErrors();
        $yield = SupplyYield::query()->firstOrFail();
        $this->assertTrue($yield->isOpen());
        $this->assertSame(0, $yield->packages());

        $this->put(route('yields.update', $yield), ['name' => 'Tambor de cera N° 1', 'started_on' => today()->subDays(5)->toDateString(),
            'ended_on' => today()->toDateString(), 'quantity_used' => '200', 'unit' => 'litros', 'packages_manual' => 28454])->assertSessionHasNoErrors();
        $yield->refresh();
        $this->assertSame(28454, $yield->packages());
        $this->assertSame(142.3, $yield->packagesPerUnit());
        $this->get(route('yields.index'))->assertOk()->assertSee('28.454')->assertSee('142,3');
    }

    public function test_quinta_yield_report_and_load_unit_price(): void
    {
        $this->actingAsRole('admin');
        $producer = Producer::query()->create(['code' => 'P1', 'name' => 'Quinta Albiero']);
        Lot::query()->create(['code' => 'LOT-1', 'date' => today(), 'producer_id' => $producer->id, 'bins' => 50, 'kg_received' => 20000, 'status' => 'open']);
        $this->get(route('reports.quintas'))->assertOk()->assertSee('Quinta Albiero')->assertSee('20.000');

        $this->post(route('loads.store'), ['date' => today()->toDateString(), 'unit_price' => '1.400'])->assertSessionHasNoErrors();
        $load = Load::query()->firstOrFail();
        $load->forceFill(['total_crates' => 778])->saveQuietly();
        $this->assertSame(1089200.0, $load->fresh()->subtotal());
        $this->get(route('loads.index'))->assertOk()->assertSee('1.089.200');
    }

    public function test_menu_shows_the_new_sections(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('dashboard'))->assertOk()->assertSee('DTV-e (SENASA)')->assertSee('Tratamientos')
            ->assertSee('Rendimiento por quinta')->assertSee('Ingresos de fruta (lotes)');
    }
}
