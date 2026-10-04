<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Errores de JavaScript en las vistas que los tests de PHP no ven al ejecutar. */
class FrontendConventionsTest extends TestCase
{
    /**
     * Alpine ya llama solo al init() del objeto de x-data: con x-init="init()" se ejecuta dos veces
     * (gráficos dibujados dos veces, temporizadores y atajos de teclado duplicados).
     */
    public function test_views_do_not_call_alpine_init_twice(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'x-init="init()"'))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Sacá x-init="init()": Alpine ya llama a init() solo.');
    }
}
