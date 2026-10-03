<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ColdRoom;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Lot;
use App\Models\Owner;
use App\Models\Packer;
use App\Models\Producer;
use App\Models\ProductionLine;
use App\Models\Provider;
use App\Models\Reason;
use App\Models\Role;
use App\Models\Season;
use App\Models\Sequence;
use App\Models\Shift;
use App\Models\Size;
use App\Models\Supply;
use App\Models\Transporter;
use App\Models\Truck;
use App\Models\User;
use App\Models\Variety;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\AuditService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos de DEMOSTRACIÓN. Nunca ejecutar sobre una base con datos reales.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Usuarios demo (contraseña común: DEMO_PASSWORD del .env, por defecto "demo1234"):
 *   admin.demo (Administrador), cliente (Portal de cliente), ingreso (Operador de ingreso), cargas (Operador de cargas),
 *   calidad (Control de calidad), facturacion (Facturación), supervisor (Supervisor),
 *   embalador (Embalador vinculado a EMB001), kiosco (Operador de ingreso en modo kiosco).
 */
class DemoSeeder extends Seeder
{
    private Warehouse $warehouse;

    private array $varieties = [];

    private array $sizes = [];

    private array $packers = [];

    /** Con un usuario: carga desde la administración general (sin usuarios demo, junto a datos que ya existan). */
    private ?User $actor = null;

    /** Días de operación simulados. */
    private int $days = 29;

    public function run(): void
    {
        if (app()->environment('production') && ! $this->command?->confirm('Está en PRODUCCIÓN. ¿Instalar datos demo de todos modos?', false)) {
            return;
        }

        $this->call(SystemSeeder::class);
        $this->seed(withUsers: true);

        $this->command?->info('Datos demo instalados. Usuarios: admin.demo, ingreso, cargas, calidad, facturacion, supervisor, embalador, kiosco, cliente.');
    }

    /**
     * «Cargar datos de ejemplo» desde la administración general: al menos 10 de cada cosa para ver cómo se ve
     * y probar todo. NO crea usuarios (en un servidor real serían cuentas con contraseña conocida) y no pisa lo
     * que ya esté cargado: los códigos siguen la numeración del sistema.
     */
    public function runFor(User $actor, int $days = 14): void
    {
        $this->actor = $actor;
        $this->days = $days;
        (new SystemSeeder)->run();
        $this->seed(withUsers: false);
    }

    private function seed(bool $withUsers): void
    {
        app(AuditService::class)->muted(function () use ($withUsers) {
            DB::transaction(function () use ($withUsers) {
                $this->warehouse = Warehouse::query()->firstOrFail();
                $this->catalogs();
                if ($withUsers) {
                    $this->users();
                }
                $this->locations();
                $this->operations();
                $this->admin();
                $this->logistics();
                $this->treasury();
                $this->extras();
            });
        });
    }

    /** Un elemento al azar (sin Faker: en el servidor no se instalan las dependencias de desarrollo). */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /** Usuario demo por nombre; cargando desde la administración general, quien la carga. */
    private function userId(string $username): ?int
    {
        return User::query()->where('username', $username)->value('id') ?? $this->actor?->id;
    }

    private ?User $previousAuth = null;

    /** Opera como el usuario indicado sin perder la sesión de quien está usando el sistema. */
    private function actAs(User $user): void
    {
        $this->previousAuth = Auth::user();
        Auth::setUser($user);
    }

    private function restoreAuth(): void
    {
        $this->previousAuth ? Auth::setUser($this->previousAuth) : Auth::forgetUser();
        $this->previousAuth = null;
    }

    private function actingUser(): User
    {
        return $this->actor ?? User::query()->where('username', 'admin.demo')->firstOrFail();
    }

    /** Próximo código de una numeración (respeta la que ya use el sistema). */
    private function sequenceStart(string $key): int
    {
        return max(1, (int) Sequence::query()->where('key', $key)->value('next_number'));
    }

    /** @var array<string, array{0: string, 1: int}> */
    private array $sequenceFormats = [];

    private function sequenceCode(string $key, int $number): string
    {
        [$prefix, $padding] = $this->sequenceFormats[$key] ??= (function () use ($key) {
            $row = Sequence::query()->where('key', $key)->first(['prefix', 'padding']);

            return $row ? [(string) $row->prefix, (int) $row->padding] : (\App\Services\SequenceService::DEFAULTS[$key] ?? ['', 6]);
        })();

        return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }

    private function catalogs(): void
    {
        // Datos de empresa de demostración (sólo si no fueron configurados).
        $settings = app(\App\Services\SettingsService::class);
        if (in_array(setting('company.name'), [null, '', 'Galpón de Empaque'], true)) {
            $settings->set('company.name', 'Empaque Demo SA');
            $settings->set('company.cuit', '30712345671');
            $settings->set('company.address', 'Ruta 9 km 1302, Tafí Viejo, Tucumán');
        }

        $varieties = [
            ['NAR-VAL', 'Naranja Valencia', 'Naranja', '#f97316'],
            ['NAR-NAV', 'Naranja Navel', 'Naranja', '#fb923c'],
            ['MAN-MUR', 'Mandarina Murcott', 'Mandarina', '#f59e0b'],
            ['MAN-OKI', 'Mandarina Okitsu', 'Mandarina', '#eab308'],
            ['LIM-EUR', 'Limón Eureka', 'Limón', '#facc15'],
            ['POM-ROJ', 'Pomelo Rojo', 'Pomelo', '#ef4444'],
            ['POM-BLA', 'Pomelo Blanco', 'Pomelo', '#fde047'],
            ['LIM-GEN', 'Limón Génova', 'Limón', '#d9f99d'],
            ['NAR-SAL', 'Naranja Salustiana', 'Naranja', '#fdba74'],
            ['MAN-ELL', 'Mandarina Ellendale', 'Mandarina', '#fcd34d'],
        ];
        foreach ($varieties as [$code, $name, $species, $color]) {
            $this->varieties[] = Variety::query()->firstOrCreate(['code' => $code], compact('name', 'species', 'color'))->id;
        }

        foreach (['40', '48', '56', '64', '70', '80', '88', '100', '113', '125'] as $i => $code) {
            $this->sizes[] = Size::query()->firstOrCreate(['code' => $code], ['name' => 'Tamaño '.$code, 'sort' => $i])->id;
        }

        $producers = [['PROD-001', 'Finca Los Naranjos', 'Famaillá'], ['PROD-002', 'Citrícola del Norte SA', 'Lules'], ['PROD-003', 'Juan Gómez', 'Monteros'],
            ['PROD-004', 'Agro Tucumán SRL', 'Concepción'], ['PROD-005', 'Finca La Esperanza', 'Famaillá'], ['PROD-006', 'Hermanos Paz', 'Bella Vista'],
            ['PROD-007', 'Cítricos Santa Rosa', 'Lules'], ['PROD-008', 'Rosa Medina', 'Monteros'], ['PROD-009', 'El Ceibo SRL', 'Aguilares'], ['PROD-010', 'Finca Don Pedro', 'Simoca']];
        foreach ($producers as [$code, $name, $locality]) {
            Producer::query()->firstOrCreate(['code' => $code], ['name' => $name, 'province' => 'Tucumán', 'locality' => $locality]);
        }
        foreach ([['OWN-001', 'Exportadora del Sur SA'], ['OWN-002', 'Frutas Argentinas SRL'], ['OWN-003', 'Finca Los Naranjos'], ['OWN-004', 'Citrus Export SA'],
            ['OWN-005', 'Agroindustrias del NOA'], ['OWN-006', 'Finca La Esperanza'], ['OWN-007', 'Frutícola Andina SRL'], ['OWN-008', 'Cooperativa Citrícola'],
            ['OWN-009', 'Hermanos Paz'], ['OWN-010', 'Del Valle Fruit SA']] as [$code, $name]) {
            Owner::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $clients = [
            ['Mercado Central de Buenos Aires', '30500001735', 'Buenos Aires', 'CABA'],
            ['Distribuidora Rosario SA', '30712345679', 'Rosario', 'Santa Fe'],
            ['Supermercados del Centro SRL', '30698765437', 'Córdoba', 'Córdoba'],
            ['Verdulería Mayorista Cuyo', '30711223349', 'Mendoza', 'Mendoza'],
            ['Frutas del Litoral SA', '30709988776', 'Paraná', 'Entre Ríos'],
            ['Mercado de Abasto de La Plata', '30700112233', 'La Plata', 'Buenos Aires'],
            ['Distribuidora Patagonia SRL', '30715566778', 'Neuquén', 'Neuquén'],
            ['Hipermercado del Norte SA', '30704455661', 'Salta', 'Salta'],
            ['Exportadora Atlántico SA', '30718899002', 'Mar del Plata', 'Buenos Aires'],
            ['Frutihortícola Santa Fe', '30713344556', 'Santa Fe', 'Santa Fe'],
        ];
        foreach ($clients as [$name, $cuit, $locality, $province]) {
            $client = Client::query()->firstOrCreate(['business_name' => $name], [
                'cuit' => $cuit, 'locality' => $locality, 'province' => $province, 'tax_condition' => 'RI',
            ]);
            Destination::query()->firstOrCreate(['name' => $locality.' — '.$name], [
                'client_id' => $client->id, 'locality' => $locality, 'province' => $province,
            ]);
        }

        $transporters = [];
        foreach ([['Transportes El Rápido SA', '30711112228'], ['Logística del Norte SRL', '30712223339'], ['Fríos Andinos SA', '30713334440'],
            ['Transporte Gómez e Hijos', '20251112223'], ['Cargas del Sur SRL', '30714445551'], ['Expreso Tucumán SA', '30715556662'],
            ['Refrigerados Ruta 9', '30716667773'], ['Transportes Medina', '20287778889'], ['Logística Cuyo SA', '30717778884'],
            ['Camiones del Valle SRL', '30718889995']] as [$name, $cuit]) {
            $transporters[] = Transporter::query()->firstOrCreate(['business_name' => $name], ['cuit' => $cuit, 'phone' => '381 4'.substr($cuit, 4, 6)]);
        }
        $trucks = [['AB123CD', 'Scania', 'R450'], ['AC456EF', 'Mercedes-Benz', 'Actros'], ['AD789GH', 'Iveco', 'Stralis'], ['AE234JK', 'Volvo', 'FH 460'],
            ['AF567LM', 'Scania', 'G410'], ['AG890NP', 'Mercedes-Benz', 'Axor'], ['AH123QR', 'Iveco', 'Tector'], ['AA456ST', 'Ford', 'Cargo 1723'],
            ['AB789UV', 'Volkswagen', 'Constellation'], ['AC012WX', 'DAF', 'XF 480']];
        foreach ($trucks as $i => [$plate, $brand, $model]) {
            Truck::query()->firstOrCreate(['plate' => $plate], [
                'brand' => $brand, 'model' => $model, 'transporter_id' => $transporters[$i]->id, 'capacity_kg' => $i % 3 === 2 ? 14000 : 28000,
                'capacity_pallets' => $i % 3 === 2 ? 12 : 26, 'type' => $i % 3 === 2 ? 'Chasis refrigerado' : 'Semirremolque refrigerado',
            ]);
        }
        $drivers = [['Carlos', 'Díaz', '25111222', 200], ['Miguel', 'Torres', '27333444', 8], ['Raúl', 'Sosa', '29555666', -5], ['Jorge', 'Paz', '26777888', 400],
            ['Daniel', 'Luna', '28999000', 150], ['Héctor', 'Ríos', '24123456', 90], ['Sergio', 'Vega', '30234567', 300], ['Oscar', 'Molina', '23345678', 25],
            ['Ramón', 'Acosta', '31456789', 500], ['Walter', 'Ibáñez', '27567890', 60]];
        foreach ($drivers as $i => [$first, $last, $dni, $days]) {
            Driver::query()->firstOrCreate(['dni' => $dni], [
                'first_name' => $first, 'last_name' => $last, 'license_number' => 'LIC-'.$dni,
                'license_expires_on' => now()->addDays($days), 'transporter_id' => $transporters[$i]->id, 'phone' => '381 15'.substr($dni, 2, 6),
            ]);
        }

        foreach ([['Cartonera Tucumana SA', '30700001112', 'Cajas, separadores'], ['Etiquetas del NOA', '30700003334', 'Etiquetas, film, cinta'],
            ['Maderera El Pallet', '30700005556', 'Pallets de madera'], ['Agroquímicos del Norte', '30700007778', 'Fungicidas, ceras'],
            ['Plásticos Industriales SA', '30700009990', 'Bins y cajones plásticos'], ['Ferretería Industrial Lules', '20300001113', 'Repuestos, herramientas'],
            ['Combustibles Ruta 38', '30700011115', 'Gasoil'], ['Electricidad Famaillá', '20300002224', 'Materiales eléctricos'],
            ['Embalajes del Sur SRL', '30700013337', 'Film stretch, zunchos'], ['Servicios de Frío NOA', '30700015559', 'Service de cámaras de frío']] as [$name, $cuit, $products]) {
            Provider::query()->firstOrCreate(['name' => $name], ['cuit' => $cuit, 'products' => $products]);
        }

        $shift = Shift::query()->where('code', 'M')->value('id');
        $names = ['Juan Pérez', 'María López', 'Pedro Ruiz', 'Ana Romero', 'Luis Castro', 'Sofía Herrera', 'Diego Morales', 'Lucía Benítez',
            'Martín Acosta', 'Valeria Medina', 'Jorge Ríos', 'Carla Suárez', 'Pablo Molina', 'Laura Ortiz', 'Hugo Paz', 'Elena Vera'];
        foreach ($names as $i => $full) {
            [$first, $last] = explode(' ', $full);
            $this->packers[] = Packer::query()->firstOrCreate(['code' => sprintf('EMB%03d', $i + 1)], [
                'first_name' => $first, 'last_name' => $last, 'dni' => (string) (30000000 + $i * 1111),
                'shift_id' => $shift, 'hired_on' => now()->subMonths(6 + $i), 'active' => true,
            ])->id;
        }

        ProductionLine::query()->firstOrCreate(['code' => 'L1'], ['name' => 'Línea 1', 'warehouse_id' => $this->warehouse->id]);
        ProductionLine::query()->firstOrCreate(['code' => 'L2'], ['name' => 'Línea 2', 'warehouse_id' => $this->warehouse->id]);
        ProductionLine::query()->firstOrCreate(['code' => 'L3'], ['name' => 'Línea 3 (limones)', 'warehouse_id' => $this->warehouse->id]);
    }

    private function users(): void
    {
        $password = Hash::make(env('DEMO_PASSWORD', 'demo1234'));
        $users = [
            ['admin.demo', 'Ana', 'Administradora', Role::ADMIN, []],
            ['ingreso', 'Iván', 'Ingreso', Role::INTAKE, []],
            ['cargas', 'Carla', 'Cargas', Role::LOADS, []],
            ['calidad', 'Cecilia', 'Calidad', Role::QUALITY, []],
            ['facturacion', 'Federico', 'Facturación', Role::BILLING, []],
            ['supervisor', 'Sergio', 'Supervisor', Role::SUPERVISOR, []],
            ['embalador', 'Juan', 'Pérez', Role::PACKER, ['packer_id' => $this->packers[0] ?? null]],
            ['kiosco', 'Puesto', 'Escaneo 1', Role::INTAKE, ['kiosk_mode' => true]],
            // Portal: cliente de la primera carga demo (entregada y facturada) y primer propietario.
            ['cliente', 'Portal', 'Cliente', Role::CLIENT, [
                'client_id' => Destination::query()->orderBy('id')->value('client_id'),
                'owner_id' => Owner::query()->orderBy('id')->value('id'),
            ]],
        ];
        foreach ($users as [$username, $first, $last, $role, $extra]) {
            $user = User::query()->firstOrCreate(['username' => $username], array_merge([
                'first_name' => $first, 'last_name' => $last, 'status' => 'active', 'password' => $password,
                'role_id' => Role::query()->where('slug', $role)->value('id'),
            ], $extra));
            $user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);
        }
    }

    private function locations(): void
    {
        // Por código: si ya existen (cargados a mano o en otra carga de ejemplos) se reutilizan.
        $make = fn (array $a) => WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => $a['code']],
            array_merge(['warehouse_id' => $this->warehouse->id], $a),
        );

        $col = 0;
        foreach (['A', 'B'] as $s) {
            $sector = $make(['type' => 'sector', 'code' => "S-$s", 'name' => "Sector $s", 'capacity_pallets' => 120, 'map_x' => $col + 1, 'map_y' => 1, 'map_w' => 4, 'map_h' => 3]);
            for ($p = 1; $p <= 5; $p++) {
                $make(['type' => 'position', 'parent_id' => $sector->id, 'code' => sprintf('%s%02d', $s, $p), 'name' => sprintf('Posición %s%02d', $s, $p), 'capacity_pallets' => 10]);
            }
            $col += 4;
        }
        $make(['type' => 'dispatch', 'code' => 'DESP', 'name' => 'Zona de despacho', 'capacity_pallets' => 20, 'map_x' => 9, 'map_y' => 1, 'map_w' => 2, 'map_h' => 3]);

        // 10 cámaras de frío (dos filas de cinco en el mapa).
        for ($n = 1; $n <= 10; $n++) {
            $location = $make(['type' => 'cold_room', 'code' => "CAM-$n", 'name' => "Cámara $n", 'capacity_pallets' => 30,
                'map_x' => 1 + (($n - 1) % 5) * 2, 'map_y' => $n <= 5 ? 4 : 6, 'map_w' => 2, 'map_h' => 2]);
            $lemons = $n >= 9;
            ColdRoom::query()->firstOrCreate(['code' => "CAM-$n"], [
                'name' => 'Cámara '.$n.($lemons ? ' (limones)' : ''), 'location_id' => $location->id,
                'temp_min' => $lemons ? 8 : 2, 'temp_max' => $lemons ? 12 : 6, 'humidity_min' => 85, 'humidity_max' => 95,
            ]);
        }
    }

    /**
     * Genera 30 días de operación: lotes, pallets, cajones procesados con su
     * registro de producción, controles de calidad, rechazos y algunos sin procesar.
     */
    private function operations(): void
    {
        if ($this->actor === null && DB::table('crates')->exists()) {
            return;
        }

        $season = Season::current()?->id;
        $operator = $this->userId('ingreso');
        $qualityUser = $this->userId('calidad');
        $producers = Producer::query()->pluck('id')->all();
        $owners = Owner::query()->pluck('id')->all();
        $drivers = Driver::query()->pluck('id')->all();
        $dtvSeq = 1;
        $shifts = Shift::query()->pluck('id', 'code');
        $lines = ProductionLine::query()->pluck('id')->all();
        // Capacidad libre por posición: los datos demo respetan la misma regla que el sistema.
        $free = WarehouseLocation::query()->whereIn('type', ['position', 'cold_room'])->pluck('capacity_pallets', 'id')
            ->map(fn ($c) => (int) $c > 0 ? (int) $c : 20)->all();
        $place = function () use (&$free): ?int {
            $available = array_keys(array_filter($free, fn ($n) => $n > 0));
            if ($available === []) {
                return null; // sin lugar: queda «sin ubicar»
            }
            $id = $available[array_rand($available)];
            $free[$id]--;

            return $id;
        };
        $rejectReasons = Reason::query()->where('type', 'reject')->pluck('id')->all();
        $wid = $this->warehouse->id;

        $palletSeq = $this->sequenceStart('pallet');
        $crateSeq = $this->sequenceStart('crate');
        $lotSeq = $this->sequenceStart('lot');

        for ($d = app()->runningUnitTests() ? ($this->actor ? 6 : 3) : $this->days; $d >= 0; $d--) {
            $day = now()->subDays($d)->startOfDay();
            if ($day->isSunday()) {
                continue;
            }
            $lotsToday = $d === 0 ? 2 : random_int(2, 3);
            for ($l = 0; $l < $lotsToday; $l++) {
                $producer = $this->pick($producers);
                $variety = $this->pick($this->varieties);
                $lot = Lot::query()->create([
                    'warehouse_id' => $wid, 'season_id' => $season, 'code' => $this->sequenceCode('lot', $lotSeq++), 'date' => $day,
                    'producer_id' => $producer, 'owner_id' => $this->pick($owners), 'variety_id' => $variety,
                    'origin' => $this->pick(['Famaillá', 'Lules', 'Monteros', 'Concepción']),
                    'field' => 'Cuadro '.random_int(1, 20), 'quantity' => 0, 'status' => $d > 2 ? 'closed' : 'open',
                    // Como la planilla de ingresos: chofer, bines y n° de DTV.
                    'driver_id' => $drivers ? $this->pick($drivers) : null, 'bins' => random_int(20, 65), 'dtv_number' => 'DTV '.($dtvSeq++).'/'.$day->format('m'),
                    'created_by' => $operator,
                ]);

                $palletsInLot = random_int(2, 4);
                for ($p = 0; $p < $palletsInLot; $p++) {
                    $receivedAt = $day->copy()->setTime(6, 0)->addMinutes(random_int(0, 240));
                    $palletId = DB::table('pallets')->insertGetId([
                        'warehouse_id' => $wid, 'season_id' => $season, 'code' => $this->sequenceCode('pallet', $palletSeq++),
                        'lot_id' => $lot->id, 'producer_id' => $producer, 'owner_id' => $lot->owner_id, 'variety_id' => $variety,
                        'origin' => $lot->origin, 'received_at' => $receivedAt, 'quantity' => 40, 'gross_weight' => 0,
                        'status' => 'with_product', 'location_id' => $place(), 'created_by' => $operator,
                        'version' => 0, 'created_at' => $receivedAt, 'updated_at' => $receivedAt,
                    ]);

                    $crates = [];
                    $records = [];
                    $histories = [];
                    $totalKg = 0;
                    for ($c = 0; $c < 40; $c++) {
                        $code = $this->sequenceCode('crate', $crateSeq++);
                        // Hoy quedan algunos cajones sin procesar (pendientes).
                        $processed = ! ($d === 0 && $c >= 30);
                        $at = $receivedAt->copy()->addMinutes(20 + $c * random_int(1, 3) + $p * 30);
                        $weight = random_int(1650, 2050) / 100;
                        $packer = $this->pick($this->packers);
                        $size = $this->pick($this->sizes);
                        $shiftId = $at->hour < 14 ? $shifts['M'] : $shifts['T'];
                        $status = $processed ? ($c % 9 === 0 ? 'rejected' : ($c % 3 === 0 ? 'approved' : 'processed')) : 'registered';
                        $crates[] = [
                            'warehouse_id' => $wid, 'season_id' => $season, 'code' => $code, 'pallet_id' => $palletId, 'lot_id' => $lot->id,
                            'producer_id' => $producer, 'owner_id' => $lot->owner_id, 'variety_id' => $variety,
                            'size_id' => $processed ? $size : null, 'packer_id' => $processed ? $packer : null,
                            'shift_id' => $processed ? $shiftId : null, 'production_line_id' => $processed ? $this->pick($lines) : null,
                            'weight' => $processed ? $weight : null, 'status' => $status,
                            'quality_status' => match ($status) { 'approved' => 'approved', 'rejected' => 'rejected', default => 'pending' },
                            'processed_at' => $processed ? $at : null, 'processed_by' => $processed ? $operator : null,
                            'version' => 0, 'created_by' => $operator, 'created_at' => $receivedAt, 'updated_at' => $at,
                        ];
                        $totalKg += $processed ? $weight : 0;
                    }
                    DB::table('crates')->insert($crates);

                    $inserted = DB::table('crates')->where('pallet_id', $palletId)->get(['id', 'code', 'status', 'packer_id', 'variety_id', 'size_id', 'weight', 'shift_id', 'production_line_id', 'processed_at', 'lot_id']);
                    foreach ($inserted as $crate) {
                        $histories[] = ['stateful_type' => 'crate', 'stateful_id' => $crate->id, 'from_state' => null, 'to_state' => 'registered', 'user_id' => $operator, 'notes' => null, 'created_at' => $receivedAt];
                        if ($crate->status === 'registered') {
                            continue;
                        }
                        $records[] = [
                            'warehouse_id' => $wid, 'crate_id' => $crate->id, 'packer_id' => $crate->packer_id, 'variety_id' => $crate->variety_id,
                            'size_id' => $crate->size_id, 'shift_id' => $crate->shift_id, 'production_line_id' => $crate->production_line_id,
                            'weight' => $crate->weight, 'weight_source' => 'manual', 'user_id' => $operator, 'recorded_at' => $crate->processed_at,
                            'idempotency_key' => (string) Str::uuid(), 'created_at' => $crate->processed_at, 'updated_at' => $crate->processed_at,
                        ];
                        $histories[] = ['stateful_type' => 'crate', 'stateful_id' => $crate->id, 'from_state' => 'registered', 'to_state' => 'processed', 'user_id' => $operator, 'notes' => null, 'created_at' => $crate->processed_at];
                        if (in_array($crate->status, ['approved', 'rejected'], true)) {
                            $controlAt = Carbon::parse($crate->processed_at)->addMinutes(15);
                            $histories[] = ['stateful_type' => 'crate', 'stateful_id' => $crate->id, 'from_state' => 'processed', 'to_state' => $crate->status, 'user_id' => $qualityUser, 'notes' => null, 'created_at' => $controlAt];
                            $qcId = DB::table('quality_controls')->insertGetId([
                                'crate_id' => $crate->id, 'result' => $crate->status, 'grade' => $crate->status === 'approved' ? 'Primera' : 'Descarte',
                                'caliber' => null, 'ripeness' => 'Óptima', 'damage_pct' => $crate->status === 'rejected' ? random_int(10, 40) : random_int(0, 3),
                                'bruise_pct' => random_int(0, 5), 'rot_pct' => $crate->status === 'rejected' ? random_int(5, 30) : 0,
                                'reject_pct' => $crate->status === 'rejected' ? 100 : 0, 'user_id' => $qualityUser,
                                'controlled_at' => $controlAt, 'created_at' => $controlAt, 'updated_at' => $controlAt,
                            ]);
                            if ($crate->status === 'rejected') {
                                DB::table('rejects')->insert([
                                    'crate_id' => $crate->id, 'lot_id' => $crate->lot_id, 'variety_id' => $crate->variety_id, 'size_id' => $crate->size_id,
                                    'packer_id' => $crate->packer_id, 'reason_id' => $this->pick($rejectReasons), 'quality_control_id' => $qcId,
                                    'weight' => $crate->weight, 'user_id' => $qualityUser, 'rejected_at' => $controlAt,
                                    'created_at' => $controlAt, 'updated_at' => $controlAt,
                                ]);
                            }
                        }
                    }
                    DB::table('production_records')->insert($records);
                    DB::table('state_histories')->insert($histories);
                    DB::table('pallets')->where('id', $palletId)->update(['gross_weight' => round($totalKg + 25, 2)]);
                }
                $lot->update(['quantity' => $palletsInLot * 40]);
            }
        }

        foreach (['pallet' => $palletSeq, 'crate' => $crateSeq, 'lot' => $lotSeq] as $key => $next) {
            Sequence::query()->where('key', $key)->update(['next_number' => $next]);
        }
    }

    private function admin(): void
    {
        $provider = Provider::query()->value('id');
        $supplies = [
            ['CAJ-18', 'Caja cartón 18 kg', 'Cajas', 'u', 1200, 2000],
            ['SEP-01', 'Separador cartón', 'Separadores', 'u', 5000, 1500],
            ['ETQ-01', 'Etiqueta térmica 100x50', 'Etiquetas', 'rollo', 12, 10],
            ['FILM-01', 'Film stretch 500 mm', 'Film', 'rollo', 3, 8],
            ['PAL-MAD', 'Pallet de madera', 'Pallets', 'u', 140, 50],
            ['CIN-01', 'Cinta zunchadora', 'Cinta', 'rollo', 25, 10],
            ['CAJ-10', 'Caja cartón 10 kg', 'Cajas', 'u', 800, 1000],
            ['CER-01', 'Cera para cítricos', 'Químicos', 'l', 180, 100],
            ['FUN-01', 'Fungicida poscosecha', 'Químicos', 'l', 40, 60],
            ['BOL-03', 'Bolsa malla 3 kg', 'Bolsas', 'u', 3000, 2000],
        ];
        foreach ($supplies as [$code, $name, $category, $unit, $stock, $min]) {
            Supply::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'unit' => $unit, 'stock' => $stock, 'min_stock' => $min,
                'unit_cost' => random_int(50, 900), 'provider_id' => $provider,
            ]);
        }

        foreach (ColdRoom::query()->whereIn('code', array_map(fn ($n) => "CAM-$n", range(1, 10)))->get() as $room) {
            $rows = [];
            for ($h = 47; $h >= 0; $h--) {
                $temp = round(4 + sin($h / 4) * 1.5 + (random_int(-5, 5) / 10), 2);
                $rows[] = [
                    'cold_room_id' => $room->id, 'temperature' => $temp, 'humidity' => random_int(86, 94), 'source' => 'manual',
                    'out_of_range' => $temp < (float) $room->temp_min || $temp > (float) $room->temp_max,
                    'recorded_at' => now()->subHours($h),
                ];
            }
            DB::table('temperature_records')->insert($rows);
        }
    }

    /**
     * Cargas de ejemplo recorriendo el circuito real con los mismos servicios que usa la
     * aplicación: armado → cierre → remito → checklist → despacho → entrega → factura (simulada).
     */
    private function logistics(): void
    {
        if ($this->actor === null && \App\Models\Load::query()->exists()) {
            return;
        }

        $user = $this->actingUser();
        $this->actAs($user);

        // Demo: las facturas en modo simulación también impactan en las cuentas corrientes.
        app(\App\Services\SettingsService::class)->set('treasury.post_test_invoices', true);

        $loads = app(\App\Services\LoadService::class);
        $remitos = app(\App\Services\RemitoService::class);
        $invoices = app(\App\Services\InvoiceService::class);
        $destinations = Destination::query()->with('client')->get();
        $trucks = Truck::query()->pluck('id')->all();
        $drivers = Driver::query()->where('license_expires_on', '>', now())->pluck('id')->all();

        $plan = [['delivered', 80], ['delivered', 60], ['delivered', 70], ['dispatched', 60], ['dispatched', 50],
            ['closed', 50], ['closed', 40], ['draft', 30], ['draft', 40], ['draft', 20]];
        foreach ($plan as $i => [$target, $quantity]) {
            $destination = $destinations[$i % $destinations->count()];
            if ($loads->takeAvailable((new \App\Models\Load)->forceFill(['warehouse_id' => $this->warehouse->id]), [], 1) === []) {
                break; // no quedan cajones disponibles para cargar
            }
            $load = $loads->create([
                'client_id' => $destination->client_id, 'destination_id' => $destination->id,
                'truck_id' => $trucks[$i % count($trucks)], 'driver_id' => $drivers[$i % count($drivers)],
                'planned_crates' => $quantity, 'date' => today()->subDays(max(0, 9 - $i)),
                'transporter_id' => Truck::query()->whereKey($trucks[$i % count($trucks)])->value('transporter_id'),
                'trailer_plate' => $i % 3 === 2 ? null : sprintf('A%s%03d%s', chr(67 + $i % 5), 120 + $i * 7, ['BD', 'FG', 'HJ', 'KL'][$i % 4]), 'guide_number' => 'DTV-'.(4500 + $i),
                'commercial_destination' => $i % 4 === 2 ? 'export' : 'domestic', 'sales_channel' => ['market', 'supermarket', 'export', 'distributor'][$i % 4],
                'sale_condition' => ['account', 'account', 'consignment', 'cash'][$i % 4], 'freight_amount' => 350000 + $i * 25000, 'unit_price' => 1400 + $i * 50,
            ], $user);
            $loads->assignCrates($load, $loads->takeAvailable($load, [], $quantity), $user);
            if ($target === 'draft') {
                continue;
            }

            $loads->close($load->fresh(), $user);
            $remito = $remitos->issue($load->fresh(), $user);
            if ($target === 'closed') {
                continue;
            }

            foreach (array_keys(\App\Models\DispatchCheck::ITEMS) as $item) {
                $loads->checkItem($load->fresh(), $item, true, $user);
            }
            $loads->dispatch($load->fresh(), $user);

            $invoice = $invoices->create([
                'client_id' => $destination->client_id,
                'load_id' => $load->id,
                'items' => array_map(fn ($row) => array_merge($row, ['unit_price' => 420]), $invoices->suggestedItems($load->fresh())),
            ], $user);

            if ($target === 'delivered') {
                $remitos->deliver($remito->fresh(), [
                    'receiver_name' => 'Recepción '.$destination->name,
                    'receiver_dni' => '30111222',
                    'signature' => 'data:image/png;base64,'.base64_encode($this->signaturePng()),
                ], $user);
                $invoices->submit($invoice, $user);
            }
        }

        $this->restoreAuth();
    }

    /** Tesorería, personal, envases y datos de etiqueta de ejemplo. */
    private function treasury(): void
    {
        if ($this->actor === null && \App\Models\ExchangeRate::query()->exists()) {
            return;
        }

        $user = $this->actingUser();
        $this->actAs($user);
        $settings = app(\App\Services\SettingsService::class);

        foreach (range(9, 0) as $i => $daysAgo) {
            \App\Models\ExchangeRate::query()->firstOrCreate(
                ['date' => today()->subDays($daysAgo)->toDateString(), 'currency' => 'USD'],
                ['buy' => 1040 + $i * 3, 'sell' => 1080 + $i * 3, 'source' => 'BNA', 'user_id' => $user->id],
            );
        }

        // Etiqueta oficial del envase (sólo lo que todavía no se configuró).
        foreach (['label.show_regulatory' => true, 'label.senasa_number' => 'E-1234', 'label.provincial_registry' => '0456',
            'label.renspa' => '13.012.0.00456/00', 'label.nominal_kg' => 18.0, 'treasury.association_fee_per_kg' => 2.5] as $key => $value) {
            if ($this->actor === null || in_array(setting($key), [null, '', false, 0, 0.0], true)) {
                $settings->set($key, $value);
            }
        }

        $containers = [['CAJ18', 'Caja de cartón 18 kg', 'box', 0.9, 18], ['CAJ20', 'Caja de cartón 20 kg', 'box', 1.0, 20], ['CAJ10', 'Caja de cartón 10 kg', 'box', 0.6, 10],
            ['CAJ15X', 'Caja exportación 15 kg', 'box', 0.8, 15], ['BIN', 'Bin plástico', 'bin', 40, 400], ['BINM', 'Bin de madera', 'bin', 55, 450],
            ['JAU20', 'Jaula cosechera 20 kg', 'crate', 2, 20], ['CAP22', 'Cajón plástico 22 kg', 'crate', 1.8, 22], ['BAN05', 'Bandeja 5 kg', 'box', 0.3, 5],
            ['MAL03', 'Bolsa malla 3 kg', 'box', 0.05, 3]];
        foreach ($containers as [$code, $name, $kind, $tare, $capacity]) {
            \App\Models\ContainerType::query()->firstOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind, 'tare_kg' => $tare, 'capacity_kg' => $capacity]);
        }
        $box = \App\Models\ContainerType::query()->where('code', 'CAJ18')->firstOrFail();
        $grades = \App\Models\Grade::query()->pluck('id', 'code');
        // Sólo los cajones que todavía no tienen envase (no pisa datos cargados).
        DB::table('crates')->whereNull('container_type_id')->update(['container_type_id' => $box->id, 'grade_id' => $grades['ELE'] ?? null]);
        DB::table('crates')->where('container_type_id', $box->id)->whereRaw('id % 4 = 0')->update(['grade_id' => $grades['EXT'] ?? null]);
        DB::table('crates')->where('container_type_id', $box->id)->whereRaw('id % 7 = 0')->update(['grade_id' => $grades['COM'] ?? null]);

        $crews = [];
        foreach ([['CUA001', 'Empaque turno mañana', 'packing', 'Ramón Quiroga'], ['CUA002', 'Cosecha finca norte', 'harvest', 'Elsa Ibáñez'],
            ['CUA003', 'Empaque turno tarde', 'packing', 'Hugo Ledesma'], ['CUA004', 'Cosecha finca sur', 'harvest', 'Marta Ojeda']] as [$code, $name, $kind, $leader]) {
            $crews[] = \App\Models\Crew::query()->firstOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind, 'leader' => $leader]);
        }
        $people = ['Juan Pereyra', 'María Gómez', 'Luis Sosa', 'Ana Díaz', 'Pedro Ruiz', 'Carla Vega', 'Raúl Herrera', 'Silvia Castro',
            'Néstor Rojas', 'Gabriela Luna', 'Mario Toledo', 'Patricia Funes'];
        $employees = [];
        foreach ($people as $i => $full) {
            [$first, $last] = explode(' ', $full);
            $crew = $crews[$i % count($crews)];
            $employees[] = \App\Models\Employee::query()->firstOrCreate(['code' => sprintf('EMP%03d', $i + 1)], [
                'first_name' => $first, 'last_name' => $last, 'dni' => (string) (30100200 + $i),
                'position' => $crew->kind === 'harvest' ? 'Cosechador' : 'Embalador', 'crew_id' => $crew->id,
                'hired_on' => today()->subMonths(6 + $i), 'daily_wage' => 28000 + ($i % 3) * 2000,
            ]);
        }

        $accounts = app(\App\Services\AccountService::class);
        $cash = app(\App\Services\CashService::class);

        // Compra de fruta: kilos y precio en lotes de ejemplo sin precio cargado; la mitad quedan liquidados.
        $lots = Lot::query()->whereNull('kg_received')->whereNull('price_per_kg')->orderByDesc('id')->limit(10)->get();
        foreach ($lots as $i => $lot) {
            $lot->update(['kg_received' => 12000 + $i * 1500, 'price_per_kg' => 180 + $i * 5, 'container_type_id' => \App\Models\ContainerType::query()->where('code', 'BIN')->value('id')]);
            if ($i % 2 === 0) {
                $accounts->settleLot($lot->fresh(), $user);
            }
        }

        // Caja del día con 10 movimientos (si ya hay una abierta, se usa esa).
        try {
            $cash->open(250000, 'Apertura (datos de ejemplo)', $user);
        } catch (\App\Exceptions\BusinessException) {
            // Ya había una caja abierta.
        }
        foreach ([['out', 'expenses', 'Cinta y precintos', 15000], ['in', 'collection', 'Cobro en efectivo Mercado Central', 180000],
            ['out', 'freight', 'Flete a Rosario (adelanto)', 90000], ['out', 'wages', 'Jornales cuadrilla de cosecha', 210000],
            ['out', 'advance', 'Adelanto a Juan Pereyra', 30000], ['in', 'bank_withdrawal', 'Extracción para pagos', 300000],
            ['out', 'provider_payment', 'Pago a Maderera El Pallet', 120000], ['out', 'expenses', 'Gasoil autoelevador', 45000],
            ['in', 'collection', 'Cobro Verdulería Mayorista Cuyo', 95000], ['out', 'bank_deposit', 'Depósito en Banco Nación', 150000]] as [$direction, $category, $description, $amount]) {
            try {
                $cash->addMovement(['direction' => $direction, 'category' => $category, 'description' => $description, 'amount' => $amount], $user);
            } catch (\App\Exceptions\BusinessException) {
                // Sin caja abierta o sin saldo: se saltea ese movimiento.
            }
        }

        $clientNames = ['Mercado Central de Buenos Aires', 'Distribuidora Rosario SA', 'Supermercados del Centro SRL', 'Verdulería Mayorista Cuyo',
            'Frutas del Litoral SA', 'Mercado de Abasto de La Plata', 'Distribuidora Patagonia SRL', 'Hipermercado del Norte SA'];
        $clients = Client::query()->whereIn('business_name', $clientNames)->orderBy('id')->get()->values();
        $banks = ['Banco Nación', 'Banco Galicia', 'Banco Macro', 'Banco Santander', 'Banco Provincia', 'BBVA', 'Banco Credicoop', 'Banco Patagonia'];
        $firstCheck = null;
        foreach ($clients as $i => $client) {
            if ($i < 2) {
                $accounts->registerPayment($client, ['direction' => 'collection', 'amount' => 300000 + $i * 150000, 'method' => 'cash', 'date' => today()->toDateString()], $user);
            }
            $movement = $accounts->registerPayment($client, ['direction' => 'collection', 'amount' => 450000 + $i * 95000, 'method' => 'check', 'date' => today()->subDays($i)->toDateString(),
                'check' => ['bank' => $banks[$i % count($banks)], 'number' => sprintf('%08d', 45871 + $i * 1013), 'payment_date' => today()->addDays(3 + $i * 6)->toDateString(),
                    'issued_on' => today()->subDays($i)->toDateString(), 'electronic' => $i % 3 === 1]], $user);
            $firstCheck ??= $movement;
        }

        $producers = Producer::query()->whereIn('code', ['PROD-001', 'PROD-002', 'PROD-003'])->get();
        foreach ($producers as $i => $producer) {
            $accounts->registerPayment($producer, ['direction' => 'payment', 'amount' => 200000 + $i * 100000, 'method' => $i === 1 ? 'transfer' : 'cash',
                'reference' => $i === 1 ? 'TRF 889123' : null, 'date' => today()->subDays($i)->toDateString()], $user);
        }
        $provider = Provider::query()->where('name', 'Cartonera Tucumana SA')->first();
        if ($provider) {
            $accounts->adjust($provider, 'opening_credit', 800000, 'Saldo inicial al pasar al sistema nuevo', today()->subDays(20)->toDateString(), $user);
            if ($firstCheck) {
                $accounts->registerPayment($provider, ['direction' => 'payment', 'method' => 'check', 'endorse_check_id' => $firstCheck->source_id, 'date' => today()->toDateString()], $user);
            }
        }
        foreach (Transporter::query()->whereIn('business_name', ['Transportes El Rápido SA', 'Logística del Norte SRL'])->get() as $i => $transporter) {
            $accounts->registerPayment($transporter, ['direction' => 'payment', 'amount' => 300000 + $i * 50000, 'method' => 'check', 'date' => today()->toDateString(),
                'check' => ['bank' => 'Banco Santander', 'number' => sprintf('%08d', 90000123 + $i), 'payment_date' => today()->addDays(15 + $i * 10)->toDateString()]], $user);
        }
        $accounts->registerPayment($employees[0], ['direction' => 'payment', 'type' => 'advance', 'amount' => 30000, 'method' => 'cash', 'date' => today()->toDateString()], $user);

        $this->restoreAuth();
    }

    /** Mantenimiento, incidentes, costos, paradas de línea y movimientos de insumos de ejemplo. */
    private function extras(): void
    {
        if ($this->actor === null && \App\Models\Machine::query()->exists()) {
            return;
        }
        $user = $this->actingUser();
        $this->actAs($user);

        $maintenance = app(\App\Services\MaintenanceService::class);
        $machines = [['MAQ-01', 'Lavadora de fruta', 'Frutec', 'LV-200'], ['MAQ-02', 'Enceradora', 'Frutec', 'EN-150'], ['MAQ-03', 'Calibradora electrónica', 'Maf Roda', 'Globalscan'],
            ['MAQ-04', 'Secadora túnel', 'Frutec', 'ST-300'], ['MAQ-05', 'Autoelevador 1', 'Toyota', '8FG25'], ['MAQ-06', 'Autoelevador 2', 'Hyster', 'H2.5FT'],
            ['MAQ-07', 'Zunchadora de pallets', 'Strapex', 'SMG 10'], ['MAQ-08', 'Equipo de frío cámara 1', 'Bitzer', 'CSH 6553'],
            ['MAQ-09', 'Equipo de frío cámara 2', 'Bitzer', 'CSH 6553'], ['MAQ-10', 'Grupo electrógeno', 'Cummins', 'C150D5']];
        foreach ($machines as $i => [$code, $name, $brand, $model]) {
            $machine = \App\Models\Machine::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'brand' => $brand, 'model' => $model, 'serial_number' => 'SN-'.(10200 + $i * 37),
                'status' => $i === 3 ? 'maintenance' : ($i === 9 ? 'out_of_service' : 'operational'),
                'next_maintenance_on' => today()->addDays([3, 12, 25, -2, 40, 8, 60, 15, 15, 90][$i]),
            ]);
            if (! $machine->wasRecentlyCreated) {
                continue;
            }
            $maintenance->register($machine, ['type' => ['preventive', 'corrective', 'preventive', 'emergency'][$i % 4], 'date' => today()->subDays(5 + $i * 3)->toDateString(),
                'technician' => ['Taller Gómez', 'Servicio oficial', 'Mantenimiento propio'][$i % 3], 'cost' => 35000 + $i * 12000,
                'parts_used' => ['Rodamientos', 'Correa', 'Filtro y aceite', 'Sensor'][$i % 4], 'notes' => 'Carga de ejemplo']);
        }

        $incidents = app(\App\Services\IncidentService::class);
        if (! \App\Models\Incident::query()->where('description', 'like', '%(ejemplo)%')->exists()) {
            $list = [['missing_crate', 'Galpón', 'Falta un cajón en el pallet al controlar la carga (ejemplo)', 'medium'],
                ['weight_error', 'Línea 1', 'La balanza marca 300 g de más (ejemplo)', 'high'],
                ['documentation', 'Despacho', 'El remito salió sin el número de guía (ejemplo)', 'low'],
                ['transport', 'Despacho', 'El camión llegó dos horas tarde (ejemplo)', 'medium'],
                ['damaged_product', 'Cámara 2', 'Cajones aplastados por mala estiba (ejemplo)', 'high'],
                ['load_error', 'Despacho', 'Se cargó un pallet de otro cliente (ejemplo)', 'critical'],
                ['other', 'Comedor', 'Corte de luz de 20 minutos (ejemplo)', 'low'],
                ['damaged_product', 'Línea 2', 'Fruta con golpes en la cinta (ejemplo)', 'medium'],
                ['weight_error', 'Ingreso', 'Diferencia de kilos con el productor (ejemplo)', 'medium'],
                ['documentation', 'Oficina', 'Factura con CUIT mal cargado (ejemplo)', 'low']];
            foreach ($list as $i => [$type, $area, $description, $priority]) {
                $incidents->create(['type' => $type, 'area' => $area, 'description' => $description, 'priority' => $priority,
                    'occurred_at' => now()->subDays($i)->subHours($i * 2)], $user);
            }
        }

        if (! \App\Models\Cost::query()->where('description', 'like', '%(ejemplo)%')->exists()) {
            foreach ([['supplies', 'Cajas de cartón (ejemplo)', 850000], ['transport', 'Flete a Buenos Aires (ejemplo)', 620000], ['labor', 'Jornales semana 1 (ejemplo)', 1400000],
                ['maintenance', 'Service calibradora (ejemplo)', 280000], ['other', 'Energía eléctrica (ejemplo)', 510000], ['supplies', 'Etiquetas y film (ejemplo)', 190000],
                ['transport', 'Flete a Mendoza (ejemplo)', 480000], ['labor', 'Jornales semana 2 (ejemplo)', 1350000], ['maintenance', 'Repuestos autoelevador (ejemplo)', 95000],
                ['other', 'Seguro del galpón (ejemplo)', 230000]] as $i => [$category, $description, $amount]) {
                \App\Models\Cost::query()->create(['category' => $category, 'description' => $description, 'amount' => $amount, 'currency' => 'ARS',
                    'date' => today()->subDays($i * 2)->toDateString(), 'user_id' => $user->id]);
            }
        }

        $reasons = Reason::query()->where('type', 'stoppage')->pluck('id')->all();
        $lines = ProductionLine::query()->pluck('id')->all();
        $shifts = Shift::query()->pluck('id')->all();
        $this->registers($user);

        if ($reasons && $lines && ! \App\Models\ProductionStoppage::query()->where('notes', 'like', '%(ejemplo)%')->exists()) {
            for ($i = 0; $i < 10; $i++) {
                $start = now()->subDays($i)->setTime(8 + ($i % 6), 15 * ($i % 4));
                $minutes = [15, 40, 25, 60, 10, 35, 20, 90, 30, 45][$i];
                \App\Models\ProductionStoppage::query()->create(['production_line_id' => $lines[$i % count($lines)], 'shift_id' => $shifts[$i % max(1, count($shifts))] ?? null,
                    'reason_id' => $reasons[$i % count($reasons)], 'started_at' => $start, 'ended_at' => $start->copy()->addMinutes($minutes),
                    'duration_minutes' => $minutes, 'user_id' => $user->id, 'notes' => 'Parada de línea (ejemplo)']);
            }
        }

        $supplies = app(\App\Services\SupplyService::class);
        if (! \App\Models\InventoryMovement::query()->where('notes', 'like', '%(ejemplo)%')->exists()) {
            foreach (\App\Models\Supply::query()->orderBy('id')->limit(10)->get() as $i => $supply) {
                $supplies->move($supply, 'in', 100 + $i * 20, ['reference' => 'Remito proveedor '.(3300 + $i), 'notes' => 'Compra (ejemplo)', 'unit_cost' => $supply->unit_cost]);
                if ((float) $supply->fresh()->stock > 10) {
                    $supplies->move($supply->fresh(), 'out', 10 + $i, ['notes' => 'Consumo en línea (ejemplo)']);
                }
            }
        }

        $this->restoreAuth();
    }

    /** DTV-e (desde lotes y cargas), tratamientos y rendimiento de cera: lo que el galpón llevaba en Excel. */
    private function registers(User $user): void
    {
        $dtv = app(\App\Services\DtvService::class);
        if (! \App\Models\DtvDocument::query()->exists()) {
            foreach (Lot::query()->with('producer', 'variety', 'driver')->whereNotNull('dtv_number')->orderByDesc('id')->limit(6)->get() as $lot) {
                $draft = $dtv->draftFromLot($lot);
                if ($draft['header']['number'] !== '' && ! \App\Models\DtvDocument::query()->where('direction', 'in')->where('number', $draft['header']['number'])->exists()) {
                    $dtv->save(null, $draft['header'], $draft['lines'], $user);
                }
            }
            $n = 14606800;
            foreach (\App\Models\Load::query()->with('client', 'destination', 'driver', 'transporter')->whereIn('status', ['closed', 'dispatched', 'delivered'])->get() as $load) {
                $draft = $dtv->draftFromLoad($load);
                $draft['header']['number'] = ($n++).'-'.random_int(1, 9);
                if ($draft['lines'] !== []) {
                    $dtv->save(null, $draft['header'], $draft['lines'], $user);
                }
            }
        }

        if (! \App\Models\Treatment::query()->exists()) {
            $types = \App\Models\Treatment::types();
            $client = Client::query()->orderBy('id')->value('id');
            foreach ([['Neuquén', 108], ['Puerto Madryn', 280], ['Puerto Madryn', 162], ['Neuquén', 108], ['Neuquén', 162], ['Neuquén', 108], ['Puerto Madryn', 540],
                ['Neuquén', 54], ['Neuquén', 108], ['Neuquén', 108]] as $i => [$destination, $qty]) {
                \App\Models\Treatment::query()->create(['date' => today()->subDays(30 - $i * 3), 'client_id' => $client, 'destination' => $destination,
                    'quantity' => $qty, 'unit' => 'Cajón', 'type' => $types[$i % 3 === 0 ? 0 : (count($types) > 1 ? 1 : 0)], 'provider' => 'Bromex',
                    'notes' => 'Carga de ejemplo', 'created_by' => $user->id]);
            }
        }

        if (! \App\Models\SupplyYield::query()->exists()) {
            $wax = \App\Models\Supply::query()->where('code', 'CER-01')->value('id');
            for ($i = 0; $i < 10; $i++) {
                $start = today()->subDays(60 - $i * 6);
                \App\Models\SupplyYield::query()->create(['supply_id' => $wax, 'name' => 'Tambor de cera N° '.($i + 1), 'started_on' => $start,
                    'ended_on' => $i === 9 ? null : $start->copy()->addDays(5), 'quantity_used' => $i === 9 ? null : 200, 'unit' => 'litros',
                    'packages_manual' => $i < 7 ? 26000 + $i * 450 : null, 'notes' => 'Carga de ejemplo', 'created_by' => $user->id]);
            }
        }
    }

    /** Firma de ejemplo (PNG generado sin ext-gd). */
    private function signaturePng(int $w = 120, int $h = 40): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = '';
        for ($y = 0; $y < $h; $y++) {
            $rows .= chr(0);
            for ($x = 0; $x < $w; $x++) {
                $ink = abs(($h / 2) + sin($x / 8) * ($h / 3) - $y) < 1.5;
                $rows .= $ink ? chr(20).chr(20).chr(60) : chr(255).chr(255).chr(255);
            }
        }

        return chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10)
            .$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($rows)).$chunk('IEND', '');
    }
}
