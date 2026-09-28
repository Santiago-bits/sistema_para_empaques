<?php

namespace Tests\Feature;

use App\Models\Empaque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpaqueQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_publica_usa_app_url(): void
    {
        config(['app.url' => 'http://192.168.0.10/sistema/public/']);
        $empaque = Empaque::factory()->create();

        $this->assertSame(
            'http://192.168.0.10/sistema/public/empaques/'.$empaque->codigo,
            $empaque->urlPublica()
        );
    }

    public function test_imagen_png(): void
    {
        $empaque = Empaque::factory()->create();

        $respuesta = $this->get(route('empaques.qr', [$empaque, 'png']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringStartsWith("\x89PNG", $respuesta->getContent());
    }

    public function test_imagen_svg(): void
    {
        $empaque = Empaque::factory()->create();

        $respuesta = $this->get(route('empaques.qr', [$empaque, 'svg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertStringContainsString('<svg', $respuesta->getContent());
    }

    public function test_formato_no_permitido_da_404(): void
    {
        $empaque = Empaque::factory()->create();

        $this->get("/empaques/{$empaque->codigo}/qr.gif")->assertNotFound();
    }

    public function test_descarga_como_archivo(): void
    {
        $empaque = Empaque::factory()->create();

        foreach (['png', 'svg'] as $formato) {
            $this->get(route('empaques.qr.descargar', [$empaque, $formato]))
                ->assertOk()
                ->assertHeader('Content-Disposition', "attachment; filename=\"qr-{$empaque->codigo}.{$formato}\"");
        }
    }

    public function test_etiqueta_muestra_qr_codigo_y_nombre(): void
    {
        $empaque = Empaque::factory()->create(['nombre' => 'Caja etiqueta']);

        $this->get(route('empaques.etiqueta', $empaque))
            ->assertOk()
            ->assertSee($empaque->codigo)
            ->assertSee('Caja etiqueta')
            ->assertSee(route('empaques.qr', [$empaque, 'svg']), false);
    }

    public function test_detalle_muestra_qr_y_acciones(): void
    {
        $empaque = Empaque::factory()->create();

        $this->get(route('empaques.show', $empaque))
            ->assertOk()
            ->assertSee(route('empaques.qr', [$empaque, 'svg']), false)
            ->assertSee(route('empaques.qr.descargar', [$empaque, 'png']), false)
            ->assertSee(route('empaques.etiqueta', $empaque), false);
    }

    public function test_empaque_eliminado_no_genera_qr(): void
    {
        $empaque = Empaque::factory()->create();
        $empaque->delete();

        $this->get(route('empaques.qr', [$empaque, 'png']))->assertNotFound();
    }
}
