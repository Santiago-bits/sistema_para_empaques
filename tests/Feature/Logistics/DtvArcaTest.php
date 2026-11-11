<?php

namespace Tests\Feature\Logistics;

use App\Models\DtvDocument;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * DTV-e como control de lo que se hace en ARCA: datos del listado de ARCA (estado, fechas, intervención),
 * filtros por origen/destino del tipo (EMP, CTC, EST), transporte y estado, e importación del listado.
 */
class DtvArcaTest extends TestCase
{
    use RefreshDatabase;

    private function doc(array $attrs): DtvDocument
    {
        return DtvDocument::query()->create($attrs + ['date' => '2026-10-02', 'direction' => 'out']);
    }

    public function test_filters_by_origin_target_transport_status_and_intervention(): void
    {
        $this->actingAsRole('admin');
        $this->doc(['number' => 'A-111', 'doc_type' => 'EMP-CTC', 'transport' => 'Lima Gustavo', 'arca_status' => 'issued', 'needs_intervention' => true]);
        $this->doc(['number' => 'B-222', 'doc_type' => 'EST-EMP', 'direction' => 'in', 'transport' => 'Comparin Federico', 'arca_status' => 'closed']);
        $this->doc(['number' => 'C-333', 'doc_type' => 'CTC-MER', 'transport' => 'Lima Gustavo', 'arca_status' => 'closed']);

        $this->get(route('dtv.index'))->assertOk()->assertSee('A-111')->assertSee('B-222')->assertSee('C-333')
            ->assertSee('Viene de')->assertSee('Centro de tratamiento cuarentenario');

        $this->get(route('dtv.index', ['origin' => 'EMP']))->assertSee('A-111')->assertDontSee('B-222')->assertDontSee('C-333');
        $this->get(route('dtv.index', ['target' => 'EMP']))->assertSee('B-222')->assertDontSee('A-111')->assertDontSee('C-333');
        $this->get(route('dtv.index', ['origin' => 'CTC']))->assertSee('C-333')->assertDontSee('A-111');
        $this->get(route('dtv.index', ['direction' => 'in']))->assertSee('B-222')->assertDontSee('A-111');
        $this->get(route('dtv.index', ['transport' => 'Lima Gustavo']))->assertSee('A-111')->assertSee('C-333')->assertDontSee('B-222');
        $this->get(route('dtv.index', ['arca_status' => 'closed']))->assertSee('B-222')->assertSee('C-333')->assertDontSee('A-111');
        $this->get(route('dtv.index', ['intervention' => 'yes']))->assertSee('A-111')->assertDontSee('B-222');
        $this->get(route('dtv.index', ['from' => '2026-10-03']))->assertDontSee('A-111');

        // Valores raros en los filtros no rompen la pantalla.
        $this->get(route('dtv.index', ['origin' => "EMP'; DROP"]))->assertSessionHasErrors('origin');
        $this->get(route('dtv.index', ['arca_status' => 'cualquiera']))->assertSessionHasErrors('arca_status');

        // La exportación respeta los filtros y trae las columnas de ARCA.
        $this->get(route('dtv.index', ['format' => 'csv', 'origin' => 'EMP']))->assertOk();
    }

    public function test_registers_a_movement_without_lines_with_arca_data(): void
    {
        $this->actingAsRole('admin');

        $this->post(route('dtv.store'), [
            'date' => '2026-10-05', 'direction' => 'out', 'number' => '14700001', 'doc_type' => 'emp-ctc ',
            'loaded_on' => '2026-10-05', 'expires_on' => '2026-10-08', 'arca_status' => 'issued', 'needs_intervention' => '1',
            'lines' => [['quantity' => '', 'unit' => 'Cajón']],
        ])->assertRedirect();

        $doc = DtvDocument::query()->where('number', '14700001')->firstOrFail();
        $this->assertSame('EMP-CTC', $doc->doc_type);
        $this->assertSame('2026-10-08', $doc->expires_on->toDateString());
        $this->assertSame('issued', $doc->arca_status);
        $this->assertTrue($doc->needs_intervention);
        $this->assertCount(0, $doc->lines);

        $this->get(route('dtv.index'))->assertOk()->assertSee('14700001')->assertSee('Emitido')->assertSee('Intervención');
        $this->get(route('dtv.show', $doc))->assertOk()->assertSee('Sin líneas')->assertSee('Estado en ARCA');
        $this->get(route('dtv.edit', $doc))->assertOk()->assertSee('Datos de ARCA');

        // Un estado inventado no se acepta.
        $this->post(route('dtv.store'), ['date' => '2026-10-05', 'direction' => 'out', 'number' => '14700002', 'arca_status' => 'hackeado'])
            ->assertSessionHasErrors('arca_status');
    }

    public function test_imports_the_arca_listing_and_updates_the_status_on_a_second_import(): void
    {
        $this->actingAsRole('admin');
        // Ya cargado a mano desde la planilla, con sus kilos.
        $existing = $this->doc(['number' => '14606819', 'doc_type' => 'EMP-CTC', 'transport' => 'Lima Gustavo']);
        $existing->lines()->create(['species' => 'Naranja', 'quantity' => 54, 'unit' => 'Cajón', 'kg_per_unit' => 18, 'kg_total' => 972]);

        $path = $this->arcaListing([
            ['0014606819', 'EMP-CTC', 'E-2929-b-C', 'CTC Mendoza', '02/10/2026', '02/10/2026', '05/10/2026', 'Emitido', 'No'],
            ['14606900', 'EST-EMP', 'Finca Albiero', 'Empaque', '03/10/2026', '03/10/2026', '06/10/2026', 'Emitido', 'Sí'],
        ]);
        $this->post(route('dtv.import'), ['file' => new UploadedFile($path, 'movimientos.xlsx', null, null, true)])
            ->assertRedirect(route('dtv.index'))->assertSessionHas('success');

        $this->assertSame(2, DtvDocument::query()->count());
        $existing->refresh();
        $this->assertSame('issued', $existing->arca_status);
        $this->assertSame('2026-10-05', $existing->expires_on->toDateString());
        $this->assertSame('E-2929-b-C', $existing->establishment); // estaba vacío: se completó
        $this->assertSame('Lima Gustavo', $existing->transport);  // lo cargado a mano no se toca
        $this->assertCount(1, $existing->lines);                  // ni sus kilos

        $incoming = DtvDocument::query()->where('number', '14606900')->firstOrFail();
        $this->assertSame('in', $incoming->direction); // EST-EMP: entra al empaque
        $this->assertTrue($incoming->needs_intervention);
        $this->assertCount(0, $incoming->lines);

        // Más tarde se cierra en ARCA: se vuelve a bajar el listado y se actualiza el estado sin duplicar.
        @unlink($path);
        $path = $this->arcaListing([
            ['14606819', 'EMP-CTC', 'E-2929-b-C', 'CTC Mendoza', '02/10/2026', '02/10/2026', '05/10/2026', 'Cerrado', 'No'],
            ['14606900', 'EST-EMP', 'Finca Albiero', 'Empaque', '03/10/2026', '03/10/2026', '06/10/2026', 'Cerrado', 'No'],
        ]);
        $this->post(route('dtv.import'), ['file' => new UploadedFile($path, 'movimientos.xlsx', null, null, true)])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'se les actualizó el estado'));
        @unlink($path);

        $this->assertSame(2, DtvDocument::query()->count());
        $this->assertSame(['closed'], DtvDocument::query()->distinct()->pluck('arca_status')->all());
        $this->assertFalse($incoming->fresh()->needs_intervention);
        $this->get(route('dtv.index', ['arca_status' => 'closed']))->assertSee('14606819')->assertSee('14606900');
    }

    public function test_permissions_and_module_switch(): void
    {
        $this->actingWithPermissions(['dtv.view']);
        $this->get(route('dtv.index'))->assertOk();
        $this->get(route('dtv.create'))->assertForbidden();
        $this->post(route('dtv.store'), ['date' => '2026-10-05', 'direction' => 'out', 'number' => '1'])->assertForbidden();

        $this->actingWithPermissions(['loads.view']);
        $this->get(route('dtv.index'))->assertForbidden();

        // Es opcional: si el galpón no lo usa, se apaga y desaparece.
        $this->actingAsRole('admin');
        app(ModuleService::class)->setEnabled('dtv', false);
        $this->get(route('dtv.index'))->assertNotFound();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('dtv.index'));
    }

    /** @param list<list<string>> $rows */
    private function arcaListing(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'arca').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['# Movimiento', 'Tipo', 'Origen', 'Destino', 'Fecha de emisión', 'Fecha de carga', 'Fecha de vencimiento', 'Estado', 'Requiere intervención']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
