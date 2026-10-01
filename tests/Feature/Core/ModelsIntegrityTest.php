<?php

namespace Tests\Feature\Core;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasStateHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\TestCase;

/** Reglas estructurales de los modelos que, si se rompen, fallan recién en producción. */
class ModelsIntegrityTest extends TestCase
{
    /** @return list<class-string<Model>> */
    private function models(): array
    {
        return array_map(fn ($f) => 'App\\Models\\'.basename($f, '.php'), glob(app_path('Models/*.php')));
    }

    public function test_audited_models_have_morph_alias(): void
    {
        $map = Relation::morphMap();
        foreach ($this->models() as $class) {
            $traits = class_uses_recursive($class);
            if (in_array(Auditable::class, $traits, true) || in_array(HasStateHistory::class, $traits, true)) {
                $this->assertContains($class, $map, "{$class} es auditado y necesita alias en Relation::enforceMorphMap");
            }
        }
    }

    public function test_no_relation_overrides_eloquent_methods(): void
    {
        $reserved = get_class_methods(Model::class);
        foreach ($this->models() as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Sólo métodos escritos en el propio archivo del modelo (no los de traits).
                if ($method->class === $class && $method->getFileName() === $reflection->getFileName() && in_array($method->name, $reserved, true) && ! in_array($method->name, ['casts'], true)) {
                    $this->fail("{$class}::{$method->name}() pisa un método de Eloquent.");
                }
            }
        }
        $this->assertTrue(true);
    }
}
