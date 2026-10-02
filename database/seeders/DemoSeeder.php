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

    public function run(): void
    {
        if (app()->environment('production') && ! $this->command?->confirm('Está en PRODUCCIÓN. ¿Instalar datos demo de todos modos?', false)) {
            return;
        }

        $this->call(SystemSeeder::class);

        app(AuditService::class)->muted(function () {
            DB::transaction(function () {
                $this->warehouse = Warehouse::query()->firstOrFail();
                $this->catalogs();
                $this->users();
                $this->locations();
                $this->operations();
                $this->admin();
                $this->logistics();
            });
        });

        $this->command?->info('Datos demo instalados. Usuarios: admin.demo, ingreso, cargas, calidad, facturacion, supervisor, embalador, kiosco, cliente.');
    }

    private function catalogs(): void
    {
        $varieties = [
            ['NAR-VAL', 'Naranja Valencia', 'Naranja', '#f97316'],
            ['NAR-NAV', 'Naranja Navel', 'Naranja', '#fb923c'],
            ['MAN-MUR', 'Mandarina Murcott', 'Mandarina', '#f59e0b'],
            ['MAN-OKI', 'Mandarina Okitsu', 'Mandarina', '#eab308'],
            ['LIM-EUR', 'Limón Eureka', 'Limón', '#facc15'],
            ['POM-ROJ', 'Pomelo Rojo', 'Pomelo', '#ef4444'],
        ];
        foreach ($varieties as [$code, $name, $species, $color]) {
            $this->varieties[] = Variety::query()->firstOrCreate(['code' => $code], compact('name', 'species', 'color'))->id;
        }

        foreach (['48', '56', '64', '70', '80', '88', '100'] as $i => $code) {
            $this->sizes[] = Size::query()->firstOrCreate(['code' => $code], ['name' => 'Tamaño '.$code, 'sort' => $i])->id;
        }

        $producers = [['PROD-001', 'Finca Los Naranjos'], ['PROD-002', 'Citrícola del Norte SA'], ['PROD-003', 'Juan Gómez'], ['PROD-004', 'Agro Tucumán SRL']];
        foreach ($producers as [$code, $name]) {
            Producer::query()->firstOrCreate(['code' => $code], ['name' => $name, 'province' => 'Tucumán', 'locality' => 'Famaillá']);
        }
        foreach ([['OWN-001', 'Exportadora del Sur SA'], ['OWN-002', 'Frutas Argentinas SRL'], ['OWN-003', 'Finca Los Naranjos']] as [$code, $name]) {
            Owner::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $clients = [
            ['Mercado Central de Buenos Aires', '30500001735', 'Buenos Aires', 'CABA'],
            ['Distribuidora Rosario SA', '30712345679', 'Rosario', 'Santa Fe'],
            ['Supermercados del Centro SRL', '30698765437', 'Córdoba', 'Córdoba'],
        ];
        foreach ($clients as [$name, $cuit, $locality, $province]) {
            $client = Client::query()->firstOrCreate(['business_name' => $name], [
                'cuit' => $cuit, 'locality' => $locality, 'province' => $province, 'tax_condition' => 'RI',
            ]);
            Destination::query()->firstOrCreate(['name' => $locality.' — '.$name], [
                'client_id' => $client->id, 'locality' => $locality, 'province' => $province,
            ]);
        }

        $transporter = Transporter::query()->firstOrCreate(['business_name' => 'Transportes El Rápido SA'], ['cuit' => '30711112228']);
        foreach ([['AB123CD', 'Scania', 'R450'], ['AC456EF', 'Mercedes-Benz', 'Actros'], ['AD789GH', 'Iveco', 'Stralis']] as [$plate, $brand, $model]) {
            Truck::query()->firstOrCreate(['plate' => $plate], [
                'brand' => $brand, 'model' => $model, 'transporter_id' => $transporter->id, 'capacity_kg' => 28000,
                'capacity_pallets' => 26, 'type' => 'Semirremolque refrigerado',
            ]);
        }
        foreach ([['Carlos', 'Díaz', '25111222', 200], ['Miguel', 'Torres', '27333444', 8], ['Raúl', 'Sosa', '29555666', -5]] as [$first, $last, $dni, $days]) {
            Driver::query()->firstOrCreate(['dni' => $dni], [
                'first_name' => $first, 'last_name' => $last, 'license_number' => 'LIC-'.$dni,
                'license_expires_on' => now()->addDays($days), 'transporter_id' => $transporter->id,
            ]);
        }

        Provider::query()->firstOrCreate(['name' => 'Cartonera Tucumana SA'], ['cuit' => '30700001112', 'products' => 'Cajas, separadores']);
        Provider::query()->firstOrCreate(['name' => 'Etiquetas del NOA'], ['cuit' => '30700003334', 'products' => 'Etiquetas, film, cinta']);

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
        if (WarehouseLocation::query()->exists()) {
            return;
        }
        $make = fn (array $a) => WarehouseLocation::query()->create(array_merge(['warehouse_id' => $this->warehouse->id], $a));

        $col = 0;
        foreach (['A', 'B'] as $s) {
            $sector = $make(['type' => 'sector', 'code' => "S-$s", 'name' => "Sector $s", 'capacity_pallets' => 120, 'map_x' => $col + 1, 'map_y' => 1, 'map_w' => 4, 'map_h' => 3]);
            for ($p = 1; $p <= 4; $p++) {
                $make(['type' => 'position', 'parent_id' => $sector->id, 'code' => sprintf('%s%02d', $s, $p), 'name' => sprintf('Posición %s%02d', $s, $p), 'capacity_pallets' => 10]);
            }
            $col += 4;
        }
        $cam1 = $make(['type' => 'cold_room', 'code' => 'CAM-1', 'name' => 'Cámara 1', 'capacity_pallets' => 30, 'map_x' => 1, 'map_y' => 4, 'map_w' => 3, 'map_h' => 2]);
        $cam2 = $make(['type' => 'cold_room', 'code' => 'CAM-2', 'name' => 'Cámara 2', 'capacity_pallets' => 30, 'map_x' => 4, 'map_y' => 4, 'map_w' => 3, 'map_h' => 2]);
        $make(['type' => 'dispatch', 'code' => 'DESP', 'name' => 'Zona de despacho', 'capacity_pallets' => 20, 'map_x' => 7, 'map_y' => 4, 'map_w' => 2, 'map_h' => 2]);

        ColdRoom::query()->firstOrCreate(['code' => 'CAM-1'], ['name' => 'Cámara 1', 'location_id' => $cam1->id, 'temp_min' => 2, 'temp_max' => 6, 'humidity_min' => 85, 'humidity_max' => 95, 'sensor_key' => Str::random(32)]);
        ColdRoom::query()->firstOrCreate(['code' => 'CAM-2'], ['name' => 'Cámara 2', 'location_id' => $cam2->id, 'temp_min' => 2, 'temp_max' => 6, 'humidity_min' => 85, 'humidity_max' => 95, 'sensor_key' => Str::random(32)]);
    }

    /**
     * Genera 30 días de operación: lotes, pallets, cajones procesados con su
     * registro de producción, controles de calidad, rechazos y algunos sin procesar.
     */
    private function operations(): void
    {
        if (DB::table('crates')->exists()) {
            return;
        }

        $faker = fake('es_AR');
        $season = Season::current()?->id;
        $operator = User::query()->where('username', 'ingreso')->value('id');
        $qualityUser = User::query()->where('username', 'calidad')->value('id');
        $producers = Producer::query()->pluck('id')->all();
        $owners = Owner::query()->pluck('id')->all();
        $shifts = Shift::query()->pluck('id', 'code');
        $lines = ProductionLine::query()->pluck('id')->all();
        $positions = WarehouseLocation::query()->whereIn('type', ['position', 'cold_room'])->pluck('id')->all();
        $rejectReasons = Reason::query()->where('type', 'reject')->pluck('id')->all();
        $wid = $this->warehouse->id;

        $palletSeq = 1;
        $crateSeq = 1;
        $lotSeq = 1;

        for ($d = app()->runningUnitTests() ? 3 : 29; $d >= 0; $d--) {
            $day = now()->subDays($d)->startOfDay();
            if ($day->isSunday()) {
                continue;
            }
            $lotsToday = $d === 0 ? 2 : random_int(2, 3);
            for ($l = 0; $l < $lotsToday; $l++) {
                $producer = $faker->randomElement($producers);
                $variety = $faker->randomElement($this->varieties);
                $lot = Lot::query()->create([
                    'warehouse_id' => $wid, 'season_id' => $season, 'code' => sprintf('LOT-%05d', $lotSeq++), 'date' => $day,
                    'producer_id' => $producer, 'owner_id' => $faker->randomElement($owners), 'variety_id' => $variety,
                    'origin' => $faker->randomElement(['Famaillá', 'Lules', 'Monteros', 'Concepción']),
                    'field' => 'Cuadro '.random_int(1, 20), 'quantity' => 0, 'status' => $d > 2 ? 'closed' : 'open',
                    'created_by' => $operator,
                ]);

                $palletsInLot = random_int(2, 4);
                for ($p = 0; $p < $palletsInLot; $p++) {
                    $receivedAt = $day->copy()->setTime(6, 0)->addMinutes(random_int(0, 240));
                    $palletId = DB::table('pallets')->insertGetId([
                        'warehouse_id' => $wid, 'season_id' => $season, 'code' => sprintf('PAL-%06d', $palletSeq++),
                        'lot_id' => $lot->id, 'producer_id' => $producer, 'owner_id' => $lot->owner_id, 'variety_id' => $variety,
                        'origin' => $lot->origin, 'received_at' => $receivedAt, 'quantity' => 40, 'gross_weight' => 0,
                        'status' => 'with_product', 'location_id' => $faker->randomElement($positions), 'created_by' => $operator,
                        'version' => 0, 'created_at' => $receivedAt, 'updated_at' => $receivedAt,
                    ]);

                    $crates = [];
                    $records = [];
                    $histories = [];
                    $totalKg = 0;
                    for ($c = 0; $c < 40; $c++) {
                        $code = sprintf('CJ-%06d', $crateSeq++);
                        // Hoy quedan algunos cajones sin procesar (pendientes).
                        $processed = ! ($d === 0 && $c >= 30);
                        $at = $receivedAt->copy()->addMinutes(20 + $c * random_int(1, 3) + $p * 30);
                        $weight = round($faker->randomFloat(2, 16.5, 20.5), 2);
                        $packer = $faker->randomElement($this->packers);
                        $size = $faker->randomElement($this->sizes);
                        $shiftId = $at->hour < 14 ? $shifts['M'] : $shifts['T'];
                        $status = $processed ? ($c % 9 === 0 ? 'rejected' : ($c % 3 === 0 ? 'approved' : 'processed')) : 'registered';
                        $crates[] = [
                            'warehouse_id' => $wid, 'season_id' => $season, 'code' => $code, 'pallet_id' => $palletId, 'lot_id' => $lot->id,
                            'producer_id' => $producer, 'owner_id' => $lot->owner_id, 'variety_id' => $variety,
                            'size_id' => $processed ? $size : null, 'packer_id' => $processed ? $packer : null,
                            'shift_id' => $processed ? $shiftId : null, 'production_line_id' => $processed ? $faker->randomElement($lines) : null,
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
                                    'packer_id' => $crate->packer_id, 'reason_id' => $faker->randomElement($rejectReasons), 'quality_control_id' => $qcId,
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
        ];
        foreach ($supplies as [$code, $name, $category, $unit, $stock, $min]) {
            Supply::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'unit' => $unit, 'stock' => $stock, 'min_stock' => $min,
                'unit_cost' => random_int(50, 900), 'provider_id' => $provider,
            ]);
        }

        foreach (ColdRoom::query()->get() as $room) {
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
        if (\App\Models\Load::query()->exists()) {
            return;
        }

        $user = User::query()->where('username', 'admin.demo')->firstOrFail();
        Auth::setUser($user);

        $loads = app(\App\Services\LoadService::class);
        $remitos = app(\App\Services\RemitoService::class);
        $invoices = app(\App\Services\InvoiceService::class);
        $destinations = Destination::query()->with('client')->get();
        $trucks = Truck::query()->pluck('id')->all();
        $drivers = Driver::query()->where('license_expires_on', '>', now())->pluck('id')->all();

        $plan = [['delivered', 80], ['dispatched', 60], ['closed', 50], ['draft', 30]];
        foreach ($plan as $i => [$target, $quantity]) {
            $destination = $destinations[$i % $destinations->count()];
            $load = $loads->create([
                'client_id' => $destination->client_id, 'destination_id' => $destination->id,
                'truck_id' => $trucks[$i % count($trucks)], 'driver_id' => $drivers[$i % count($drivers)],
                'planned_crates' => $quantity, 'date' => today()->subDays(3 - $i),
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

        Auth::forgetUser();
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
