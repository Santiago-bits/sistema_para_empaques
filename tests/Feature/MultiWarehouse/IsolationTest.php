<?php

namespace Tests\Feature\MultiWarehouse;

use App\Models\Client;
use App\Models\Crate;
use App\Models\Driver;
use App\Models\Load;
use App\Models\Producer;
use App\Models\Role;
use App\Models\Transporter;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Support\CurrentWarehouse;
use App\Support\Tenancy;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Multigalpón: el galpón A tiene TODOS los datos de demostración; el galpón B, recién creado, no tiene nada.
 * El administrador de B recorre todas las pantallas, busca, exporta y prueba abrir por URL cada registro de A:
 * nunca debe ver ni tocar un dato de A.
 */
class IsolationTest extends TestCase
{
    use RefreshDatabase;

    /** Parámetro de ruta => modelo (como en ScreensCrawlTest). */
    private const PARAMS = [
        'user' => User::class, 'log' => \App\Models\AuditLog::class, 'lot' => \App\Models\Lot::class, 'pallet' => \App\Models\Pallet::class,
        'crate' => Crate::class, 'packer' => \App\Models\Packer::class, 'location' => \App\Models\WarehouseLocation::class,
        'supply' => \App\Models\Supply::class, 'machine' => \App\Models\Machine::class, 'coldRoom' => \App\Models\ColdRoom::class,
        'qualityControl' => \App\Models\QualityControl::class, 'import' => \App\Models\ImportBatch::class, 'load' => Load::class,
        'remito' => \App\Models\Remito::class, 'invoice' => \App\Models\Invoice::class, 'document' => \App\Models\Document::class,
        'incident' => \App\Models\Incident::class, 'alert' => \App\Models\Alert::class, 'ticket' => \App\Models\SupportTicket::class,
        'closing' => \App\Models\DailyClosing::class, 'cost' => \App\Models\Cost::class, 'dtv' => \App\Models\DtvDocument::class,
        'treatment' => \App\Models\Treatment::class, 'reject' => \App\Models\Reject::class, 'yield' => \App\Models\SupplyYield::class,
        'session' => \App\Models\CashSession::class, 'check' => \App\Models\Check::class, 'role' => Role::class,
    ];

    private const SKIP = ['/^api\./', '/^install\./', '/^login$/', '/heartbeat/', '/download/', '/^storage\./', '/^central\./', '/^superadmin\./',
        '/^branding\./', '/^remitos\.public$/', '/^password\./', '/^owner-recovery/'];

    private Warehouse $a;

    private Warehouse $b;

    private User $adminB;

    /** @var list<string> Datos de A que nunca pueden aparecer en B. */
    private array $fingerprints = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->a = Warehouse::query()->sole();
        $this->assertTrue(Tenancy::ready());

        $super = User::factory()->role(Role::SUPER_ADMIN)->create();
        $this->b = app(WarehouseService::class)->create(['name' => 'Galpón Beta', 'cuit' => '30500001735'], $super);
        $this->adminB = app(WarehouseService::class)->createAdmin($this->b, [
            'first_name' => 'Bea', 'last_name' => 'Beta', 'username' => 'admin_beta', 'password' => 'clave1234',
        ], $super);
        $this->adminB->forceFill(['must_change_password' => false])->save();
        CurrentWarehouse::reset();

        // Huellas de A: nombres y códigos propios de sus datos.
        CurrentWarehouse::runFor($this->a->id, function () {
            $this->fingerprints = array_values(array_filter(array_unique(array_merge(
                Producer::query()->pluck('name')->all(),
                Client::query()->pluck('business_name')->all(),
                Transporter::query()->pluck('business_name')->all(),
                Driver::query()->pluck('last_name')->all(),
                // (Los códigos numerados —CJ-000001, CARG-00001— no sirven de huella: cada galpón numera desde 1.)
                \App\Models\Truck::query()->pluck('plate')->all(),
                \App\Models\Employee::query()->pluck('last_name')->all(),
            )), fn ($v) => is_string($v) && mb_strlen($v) >= 6));
        });
        $this->assertGreaterThan(20, count($this->fingerprints), 'El galpón A debe tener datos');
    }

    public function test_every_screen_of_b_shows_nothing_from_a(): void
    {
        $this->actingAs($this->adminB);
        $this->assertSame($this->b->id, CurrentWarehouse::id());

        $leaks = [];
        $errors = [];
        $visited = 0;
        foreach ($this->getRoutes() as $route) {
            $url = $this->urlWithOwnData($route);
            if ($url === null) {
                continue;
            }
            $response = $this->get($url);
            $visited++;
            if ($response->getStatusCode() >= 500) {
                $errors[] = $response->getStatusCode().' '.$route->getName().' '.Str::limit((string) $response->exception?->getMessage(), 160);
                continue;
            }
            $content = (string) $response->getContent();
            foreach ($this->fingerprints as $fp) {
                if (str_contains($content, e($fp))) {
                    $leaks[] = $route->getName()." muestra «{$fp}» del galpón A";
                    break;
                }
            }
        }

        $this->assertGreaterThan(80, $visited);
        $this->assertSame([], $errors, "Pantallas con error en el galpón B:\n".implode("\n", $errors));
        $this->assertSame([], $leaks, "Datos del galpón A visibles en B:\n".implode("\n", $leaks));
    }

    public function test_records_of_a_cannot_be_opened_from_b_by_url(): void
    {
        $this->actingAs($this->adminB);
        $opened = [];
        $tried = 0;

        foreach ($this->getRoutes() as $route) {
            $params = $route->parameterNames();
            if (count($params) !== 1) {
                continue;
            }
            $param = $params[0];
            $id = null;
            if ($param === 'record' && isset($route->defaults['catalog'])) {
                $model = \App\Catalogs\CatalogRegistry::get($route->defaults['catalog'])->modelClass();
                $id = $model::query()->withoutGlobalScopes()->where('warehouse_id', $this->a->id)->value('id');
            } elseif (isset(self::PARAMS[$param])) {
                $model = self::PARAMS[$param];
                $query = $model::query()->withoutGlobalScopes();
                $id = match (true) {
                    $model === User::class => $query->where('warehouse_id', $this->a->id)->value('id'),
                    $model === Role::class => $query->where('warehouse_id', $this->a->id)->value('id'),
                    default => $query->where('warehouse_id', $this->a->id)->value('id'),
                };
            }
            if (! $id) {
                continue;
            }
            $tried++;
            $response = $this->get(route($route->getName(), [$param => $id]));
            if ($response->getStatusCode() < 400 && ! $response->isRedirect()) {
                $opened[] = $route->getName().' abrió el registro '.$id.' del galpón A';
            }
            if ($response->getStatusCode() >= 500) {
                $opened[] = $route->getName().' dio error '.$response->getStatusCode();
            }
        }

        $this->assertGreaterThan(30, $tried);
        $this->assertSame([], $opened, "Registros de A abiertos desde B:\n".implode("\n", $opened));
    }

    public function test_search_exports_and_api_of_b_never_return_data_of_a(): void
    {
        $this->actingAs($this->adminB);
        $search = app(\App\Services\SearchService::class);
        foreach ($this->fingerprints as $fp) {
            $this->get(route('search', ['q' => $fp]))->assertOk();
            $found = json_encode($search->search($this->adminB, $fp), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString($fp, (string) $found, "La búsqueda de B encontró «{$fp}» de A");
            $this->assertNull($search->exactMatch($this->adminB, $fp), "La búsqueda exacta de B llevó a «{$fp}» de A");
        }

        foreach (\App\Catalogs\CatalogRegistry::all() as $key => $definition) {
            if (! \Illuminate\Support\Facades\Route::has($definition->routeName().'.index')) {
                continue;
            }
            $response = $this->get(route($definition->routeName().'.index', ['format' => 'csv']));
            if ($response->getStatusCode() !== 200) {
                continue;
            }
            $base = $response->baseResponse;
            $csv = match (true) {
                $base instanceof \Symfony\Component\HttpFoundation\StreamedResponse => $response->streamedContent(),
                $base instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse => file_get_contents($base->getFile()->getPathname()),
                default => (string) $base->getContent(),
            };
            foreach ($this->fingerprints as $fp) {
                $this->assertStringNotContainsString($fp, (string) $csv, "La exportación de {$key} de B trae «{$fp}» de A");
            }
        }

        // API con un token de B: sólo datos de B.
        $token = $this->adminB->createToken('test', ['read'])->plainTextToken;
        auth()->forgetGuards();
        CurrentWarehouse::reset();
        foreach (['api.v1.crates.index', 'api.v1.loads.index', 'api.v1.pallets.index', 'api.v1.lots.index'] as $name) {
            if (! \Illuminate\Support\Facades\Route::has($name)) {
                continue;
            }
            $json = (string) $this->withToken($token)->getJson(route($name))->getContent();
            foreach ($this->fingerprints as $fp) {
                $this->assertStringNotContainsString($fp, $json, "La API ({$name}) de B devuelve «{$fp}» de A");
            }
        }
    }

    public function test_b_cannot_point_to_records_of_a_and_can_reuse_its_codes(): void
    {
        $this->actingAs($this->adminB);
        $producerA = Producer::query()->withoutGlobalScopes()->where('warehouse_id', $this->a->id)->firstOrFail();
        $varietyA = \App\Models\Variety::query()->withoutGlobalScopes()->where('warehouse_id', $this->a->id)->firstOrFail();

        // Un lote de B no puede usar el productor ni la variedad de A (para B no existen).
        $this->post(route('lots.store'), ['producer_id' => $producerA->id, 'variety_id' => $varietyA->id, 'date' => today()->toDateString(),
            'kg_received' => 1000])->assertSessionHasErrors(['producer_id']);

        // B puede tener su propio productor con el MISMO código que uno de A.
        $this->post(route('catalogs.producers.store'), ['code' => $producerA->code, 'name' => 'Productor de Beta', 'active' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, Producer::query()->withoutGlobalScopes()->where('code', $producerA->code)->count());
        $this->assertSame(1, Producer::query()->count()); // B ve sólo el suyo

        // Numeraciones propias: el primer cajón de B es el N° 1 de B.
        $crate = app(\App\Services\CrateService::class)->create(['weight' => 18], $this->adminB);
        $this->assertStringEndsWith('000001', $crate->code);
        $this->assertSame($this->b->id, $crate->warehouse_id);

        // La configuración de B es de B.
        $this->assertSame('Galpón Beta', setting('company.name'));
        CurrentWarehouse::runFor($this->a->id, fn () => $this->assertNotSame('Galpón Beta', setting('company.name')));
    }

    /** @return iterable<Route> */
    private function getRoutes(): iterable
    {
        foreach (RouteFacade::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || ! $route->getName()) {
                continue;
            }
            if (collect(self::SKIP)->contains(fn ($p) => preg_match($p, $route->getName()))) {
                continue;
            }
            yield $route;
        }
    }

    private function urlWithOwnData(Route $route): ?string
    {
        $params = [];
        foreach ($route->parameterNames() as $param) {
            if (array_key_exists($param, $route->defaults)) {
                continue;
            }
            if ($param === 'record' && isset($route->defaults['catalog'])) {
                $id = \App\Catalogs\CatalogRegistry::get($route->defaults['catalog'])->modelClass()::query()->value('id');
            } elseif ($param === 'type' && in_array('holder', $route->parameterNames(), true)) {
                $params['type'] = 'client';
                continue;
            } elseif ($param === 'holder') {
                $id = Client::query()->value('id');
            } elseif ($param === 'type') {
                $params[$param] = 'producers';
                continue;
            } elseif ($param === 'report') {
                $params[$param] = 'production';
                continue;
            } else {
                $model = self::PARAMS[$param] ?? null;
                $id = $model ? $model::query()->when($model === Role::class, fn ($q) => $q->where('slug', '!=', Role::SUPER_ADMIN))
                    ->when($model === User::class, fn ($q) => $q->visibleTo())->value('id') : null;
            }
            if (! $id) {
                return null;
            }
            $params[$param] = $id;
        }

        return route($route->getName(), $params);
    }
}
