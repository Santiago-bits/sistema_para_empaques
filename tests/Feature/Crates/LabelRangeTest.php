<?php

namespace Tests\Feature\Crates;

use App\Services\SequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesProductionData;
use Tests\TestCase;

/** Etiquetas por rango: se escribe sólo el número, «CJ-» va solo y «hasta» se propone de a 50. */
class LabelRangeTest extends TestCase
{
    use CreatesProductionData, RefreshDatabase;

    public function test_codes_are_built_from_the_number(): void
    {
        $seq = app(SequenceService::class);
        $this->assertSame('CJ-000101', $seq->normalize('crate', '101'));
        $this->assertSame('CJ-000001', $seq->normalize('crate', 'CJ-00001'));   // menos ceros
        $this->assertSame('CJ-000007', $seq->normalize('crate', ' cj-7 '));      // minúsculas y espacios
        $this->assertSame('OTRO-5', $seq->normalize('crate', 'OTRO-5'));         // otro formato: tal cual
        $this->assertSame(123, $seq->numberOf('crate', 'CJ-000123'));
        $this->assertNull($seq->numberOf('crate', 'PAL-000123'));
    }

    public function test_form_suggests_the_current_block_of_50(): void
    {
        $this->actingAsRole('admin');
        $this->get(route('labels.crates'))->assertOk()->assertSee('CJ-')
            ->assertSee('from: 1,', false)->assertSee('to: 50,', false)->assertSee('Se imprimen');

        $this->crate('CJ-000123');
        $this->get(route('labels.crates'))->assertOk()->assertSee('from: 101,', false)->assertSee('to: 150,', false);
        // Generar cajones nuevos: de a 50 por defecto.
        $this->get(route('labels.crates'))->assertSee('name="quantity"', false)->assertSee('value="50"', false);
    }

    public function test_printing_by_number_finds_the_crates_even_with_fewer_zeros(): void
    {
        $this->actingAsRole('admin');
        foreach (['CJ-000001', 'CJ-000002', 'CJ-000003', 'CJ-000060'] as $code) {
            $this->crate($code);
        }

        $this->get(route('labels.crates', ['from' => '1', 'to' => '50']))->assertOk()
            ->assertSee('CJ-000001')->assertSee('CJ-000003')->assertDontSee('CJ-000060');
        // Como en la captura: «CJ-00001» con un cero menos.
        $this->get(route('labels.crates', ['from' => 'CJ-00001', 'to' => 'CJ-000002']))->assertOk()
            ->assertSee('CJ-000001')->assertSee('CJ-000002')->assertDontSee('CJ-000003');
        // Sólo «desde»: imprime ése.
        $this->get(route('labels.crates', ['from' => '60']))->assertOk()->assertSee('CJ-000060');
    }

    public function test_invalid_ranges_explain_what_is_wrong(): void
    {
        $this->actingAsRole('admin');
        $this->crate('CJ-000010');
        $this->from(route('labels.crates'))->get(route('labels.crates', ['from' => '50', 'to' => '10']))
            ->assertRedirect()->assertSessionHas('error', '«Hasta» tiene que ser mayor o igual que «Desde».');
        $this->from(route('labels.crates'))->get(route('labels.crates', ['from' => '1', 'to' => '1000']))
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'como mucho 500'));
    }
}
