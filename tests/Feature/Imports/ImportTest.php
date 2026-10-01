<?php

namespace Tests\Feature\Imports;

use App\Models\ImportBatch;
use App\Models\Producer;
use App\Models\Variety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('datos.csv', $content);
    }

    public function test_pages_and_template(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('imports.index'))->assertOk();
        $this->get(route('imports.create'))->assertOk();
        $this->get(route('imports.template', 'producers'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_upload_validates_without_importing_until_confirmed(): void
    {
        $this->actingAsRole('admin');
        Producer::query()->create(['code' => 'EXISTE', 'name' => 'Ya existe']);
        $headers = implode(';', app(\App\Services\ImportService::class)->templateHeaders('producers'));

        $file = $this->csv($headers."\nPRD-1;Finca Uno\nPRD-2;Finca Dos\nPRD-1;Repetido en archivo\nEXISTE;Duplicado en base\n;Sin código\n");
        $this->post(route('imports.store'), ['type' => 'producers', 'file' => $file])->assertRedirect();

        $batch = ImportBatch::query()->firstOrFail();
        $this->assertSame('validated', $batch->status);
        $this->assertSame(5, $batch->total_rows);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(3, $batch->error_rows);
        $this->assertSame(1, Producer::query()->count(), 'No debe importar nada antes de confirmar');

        $this->get(route('imports.show', $batch))->assertOk()->assertSee('Confirmar importación');
        $this->get(route('imports.errors', $batch))->assertOk();

        $this->post(route('imports.confirm', $batch))->assertSessionHas('success');
        $this->assertSame(3, Producer::query()->count());
        $this->assertSame('imported', $batch->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'import']);

        // Confirmar de nuevo no duplica.
        $this->post(route('imports.confirm', $batch))->assertSessionHas('error');
        $this->assertSame(3, Producer::query()->count());
    }

    public function test_discard_leaves_database_untouched(): void
    {
        $this->actingAsRole('admin');
        $headers = implode(';', app(\App\Services\ImportService::class)->templateHeaders('varieties'));
        $this->post(route('imports.store'), ['type' => 'varieties', 'file' => $this->csv($headers."\nNAR;Naranja\n")]);
        $batch = ImportBatch::query()->firstOrFail();

        $this->post(route('imports.discard', $batch))->assertRedirect(route('imports.index'));
        $this->assertSame('discarded', $batch->fresh()->status);
        $this->assertSame(0, Variety::query()->count());
    }

    public function test_requires_permission(): void
    {
        $this->actingAsRole('intake_operator');
        $this->get(route('imports.index'))->assertForbidden();
    }

    public function test_rejects_non_spreadsheet_file(): void
    {
        $this->actingAsRole('admin');
        $this->post(route('imports.store'), ['type' => 'producers', 'file' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors('file');
    }
}
