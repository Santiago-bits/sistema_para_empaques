<?php

namespace Tests\Feature;

use App\Models\Empaque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_del_lector(): void
    {
        $this->get(route('lector'))
            ->assertOk()
            ->assertSee('Activar cámara')
            ->assertSee('html5-qrcode', false);
    }

    public function test_url_leida_del_qr_redirige_al_empaque(): void
    {
        $empaque = Empaque::factory()->create();

        // El QR puede haberse impreso con otro dominio: solo importa el código.
        $this->get(route('lector.buscar', ['codigo' => 'https://otro-dominio.com/x/empaques/'.$empaque->codigo]))
            ->assertRedirect(route('empaques.show', $empaque));
    }

    public function test_codigo_tipeado_en_minusculas(): void
    {
        $empaque = Empaque::factory()->create();

        $this->get(route('lector.buscar', ['codigo' => ' '.strtolower($empaque->codigo).' ']))
            ->assertRedirect(route('empaques.show', $empaque));
    }

    public function test_codigo_inexistente(): void
    {
        $this->get(route('lector.buscar', ['codigo' => 'EMP-ZZZZZZ']))
            ->assertRedirect(route('lector'))
            ->assertSessionHas('error', 'No existe ningún empaque con el código EMP-ZZZZZZ.');
    }

    public function test_qr_ajeno_al_sistema(): void
    {
        $this->get(route('lector.buscar', ['codigo' => 'https://google.com']))
            ->assertRedirect(route('lector'))
            ->assertSessionHas('error', 'El código leído no pertenece a este sistema.');
    }

    public function test_codigo_vacio(): void
    {
        $this->get(route('lector.buscar', ['codigo' => '']))
            ->assertSessionHasErrors('codigo');
    }

    public function test_empaque_dado_de_baja_redirige_y_avisa(): void
    {
        $empaque = Empaque::factory()->create();
        $empaque->delete();

        $this->get(route('lector.buscar', ['codigo' => $empaque->codigo]))
            ->assertRedirect(route('empaques.show', $empaque));

        $this->get(route('empaques.show', $empaque))
            ->assertOk()
            ->assertSee('Empaque dado de baja')
            ->assertDontSee('Editar');
    }

    public function test_pagina_404_en_espanol(): void
    {
        $this->get('/empaques/EMP-ZZZZZZ')
            ->assertNotFound()
            ->assertSee('No encontrado')
            ->assertSee(route('lector'), false);
    }
}
