<?php

namespace Tests\Feature\Smoke;

use App\Models\Module;
use App\Models\User;
use App\Services\ModuleService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Recorre TODAS las pantallas GET del sistema con datos de demostración cargados,
 * como Super Administrador y como cada rol, y verifica que ninguna devuelva un
 * error del servidor. Las rutas con parámetros usan el primer registro existente.
 */
class ScreensCrawlTest extends TestCase
{
    use RefreshDatabase;

    /** Parámetro de ruta => modelo para resolver un ejemplo. */
    private const PARAMS = [
        'user' => \App\Models\User::class,
        'role' => \App\Models\Role::class,
        'log' => \App\Models\AuditLog::class,
        'lot' => \App\Models\Lot::class,
        'pallet' => \App\Models\Pallet::class,
        'crate' => \App\Models\Crate::class,
        'packer' => \App\Models\Packer::class,
        'location' => \App\Models\WarehouseLocation::class,
        'supply' => \App\Models\Supply::class,
        'machine' => \App\Models\Machine::class,
        'coldRoom' => \App\Models\ColdRoom::class,
        'qualityControl' => \App\Models\QualityControl::class,
        'import' => \App\Models\ImportBatch::class,
        'load' => \App\Models\Load::class,
        'remito' => \App\Models\Remito::class,
        'invoice' => \App\Models\Invoice::class,
        'document' => \App\Models\Document::class,
        'incident' => \App\Models\Incident::class,
        'alert' => \App\Models\Alert::class,
        'ticket' => \App\Models\SupportTicket::class,
        'backup' => \App\Models\Backup::class,
        'license' => \App\Models\License::class,
        'closing' => \App\Models\DailyClosing::class,
        'cost' => \App\Models\Cost::class,
    ];

    /** Rutas que no son pantallas navegables (descargas, JSON de soporte, impresión directa). */
    private const SKIP = ['/^api\./', '/^install\./', '/^login$/', '/heartbeat/', '/\.pdf$/', '/download/', '/^imports\.(errors|template)$/',
        '/^production\.scan\.(lookup|scale)$/', '/^locations\.(lookup|content)$/', '/^quality\.lookup$/', '/^storage\./', '/\.data$/'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->assertSame(4, \App\Models\Load::query()->count(), 'El seeder demo debe generar cargas');
        $overfull = \App\Models\WarehouseLocation::query()->where('capacity_pallets', '>', 0)
            ->withCount(['pallets'])->get()->filter(fn ($l) => $l->pallets_count > $l->capacity_pallets);
        $this->assertCount(0, $overfull, 'Los datos demo no pueden superar la capacidad de las ubicaciones');
        $this->assertSame(1, \App\Models\Invoice::query()->where('status', 'authorized')->count());
        $this->assertSame(1, \App\Models\Remito::query()->where('status', 'delivered')->count());
        Module::query()->update(['enabled' => true]);
        app(ModuleService::class)->flush();
    }

    public function test_every_screen_renders_for_super_admin(): void
    {
        $admin = User::factory()->role('super_admin')->create();
        $failures = $this->crawl($admin);
        $this->assertSame([], $failures, "Pantallas con error:\n".implode("\n", $failures));
    }

    public function test_every_screen_responds_without_server_error_for_each_role(): void
    {
        foreach (['admin', 'intake_operator', 'loads_operator', 'quality', 'billing', 'supervisor', 'packer'] as $role) {
            $user = User::factory()->role($role)->create();
            $failures = $this->crawl($user, allowForbidden: true);
            $this->assertSame([], $failures, "Rol {$role} - pantallas con error:\n".implode("\n", $failures));
        }
    }

    /** @return list<string> */
    private function crawl(User $user, bool $allowForbidden = false): array
    {
        $this->actingAs($user);
        $failures = [];
        $visited = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (! in_array('GET', $route->methods(), true) || ! $route->getName()) {
                continue;
            }
            $name = $route->getName();
            if (collect(self::SKIP)->contains(fn ($p) => preg_match($p, $name))) {
                continue;
            }

            $url = $this->urlFor($route);
            if ($url === null) {
                continue;
            }

            $response = $this->get($url);
            $visited++;
            $status = $response->getStatusCode();
            $ok = $status < 400 || ($allowForbidden && in_array($status, [403, 404], true));
            if (! $ok) {
                $message = $response->exception ? Str::limit($response->exception->getMessage(), 200) : '';
                $failures[] = "{$status} {$name} {$url} {$message}";
            }
        }

        $this->assertGreaterThan(40, $visited, 'Se esperaban recorrer muchas pantallas');

        return $failures;
    }

    private function urlFor(Route $route): ?string
    {
        $params = [];
        foreach ($route->parameterNames() as $param) {
            if (array_key_exists($param, $route->defaults)) {
                continue;
            }
            if ($param === 'record' && isset($route->defaults['catalog'])) {
                $definition = \App\Catalogs\CatalogRegistry::get($route->defaults['catalog']);
                $id = $definition->modelClass()::query()->value('id');
                if (! $id) {
                    return null;
                }
                $params[$param] = $id;
                continue;
            }
            if ($param === 'type') {
                $params[$param] = 'producers';
                continue;
            }
            $model = self::PARAMS[$param] ?? null;
            $id = $model ? $model::query()->value('id') : null;
            if (! $id) {
                return null;
            }
            $params[$param] = $id;
        }

        return route($route->getName(), $params);
    }
}
