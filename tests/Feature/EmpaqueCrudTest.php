<?php

namespace Tests\Feature;

use App\Enums\EstadoEmpaque;
use App\Models\Empaque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpaqueCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicio_redirige_al_listado(): void
    {
        $this->get('/')->assertRedirect(route('empaques.index'));
    }

    public function test_listado_muestra_empaques(): void
    {
        $empaque = Empaque::factory()->create(['nombre' => 'Caja listada']);

        $this->get(route('empaques.index'))
            ->assertOk()
            ->assertSee($empaque->codigo)
            ->assertSee('Caja listada');
    }

    public function test_busqueda_filtra_por_nombre_y_codigo(): void
    {
        $buscado = Empaque::factory()->create(['nombre' => 'Caja azul']);
        $otro = Empaque::factory()->create(['nombre' => 'Caja roja']);

        $this->get(route('empaques.index', ['buscar' => 'azul']))
            ->assertSee($buscado->codigo)
            ->assertDontSee($otro->codigo);

        $this->get(route('empaques.index', ['buscar' => $otro->codigo]))
            ->assertSee($otro->codigo)
            ->assertDontSee($buscado->codigo);
    }

    public function test_filtro_por_estado(): void
    {
        $entregado = Empaque::factory()->create(['estado' => EstadoEmpaque::Entregado]);
        $pendiente = Empaque::factory()->create(['estado' => EstadoEmpaque::Pendiente]);

        $this->get(route('empaques.index', ['estado' => 'entregado']))
            ->assertSee($entregado->codigo)
            ->assertDontSee($pendiente->codigo);
    }

    public function test_crear_empaque_genera_codigo(): void
    {
        $respuesta = $this->post(route('empaques.store'), [
            'nombre' => 'Caja nueva',
            'descripcion' => 'Contenido de prueba',
            'estado' => 'pendiente',
            'codigo' => 'HACKEADO', // no debe poder asignarse
        ]);

        $empaque = Empaque::firstOrFail();
        $respuesta->assertRedirect(route('empaques.show', $empaque));
        $this->assertMatchesRegularExpression('/^EMP-[A-Z2-9]{6}$/', $empaque->codigo);
        $this->assertSame('Caja nueva', $empaque->nombre);
    }

    public function test_validacion_al_crear(): void
    {
        $this->post(route('empaques.store'), [
            'nombre' => '',
            'estado' => 'inventado',
        ])->assertSessionHasErrors(['nombre', 'estado']);

        $this->assertDatabaseCount('empaques', 0);
    }

    public function test_detalle_se_busca_por_codigo(): void
    {
        $empaque = Empaque::factory()->create();

        $this->get('/empaques/'.$empaque->codigo)
            ->assertOk()
            ->assertSee($empaque->nombre);

        $this->get('/empaques/EMP-NOEXIS')->assertNotFound();
    }

    public function test_editar_empaque_no_cambia_codigo(): void
    {
        $empaque = Empaque::factory()->create(['estado' => EstadoEmpaque::Pendiente]);
        $codigoOriginal = $empaque->codigo;

        $this->put(route('empaques.update', $empaque), [
            'nombre' => 'Nombre editado',
            'estado' => 'despachado',
            'codigo' => 'OTRO-123',
        ])->assertRedirect(route('empaques.show', $empaque));

        $empaque->refresh();
        $this->assertSame('Nombre editado', $empaque->nombre);
        $this->assertSame(EstadoEmpaque::Despachado, $empaque->estado);
        $this->assertSame($codigoOriginal, $empaque->codigo);
    }

    public function test_eliminar_empaque_hace_soft_delete(): void
    {
        $empaque = Empaque::factory()->create();

        $this->delete(route('empaques.destroy', $empaque))
            ->assertRedirect(route('empaques.index'));
        $this->get(route('empaques.index'))->assertSee("Empaque {$empaque->codigo} eliminado.");

        $this->assertSoftDeleted($empaque);
        $this->get(route('empaques.index'))->assertDontSee($empaque->codigo);

        // Su detalle (lo que abre el QR impreso) avisa que fue dado de baja; editar ya no es posible.
        $this->get(route('empaques.show', $empaque))->assertOk()->assertSee('Empaque dado de baja');
        $this->get(route('empaques.edit', $empaque))->assertNotFound();
    }
}
