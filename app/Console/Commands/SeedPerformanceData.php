<?php

namespace App\Console\Commands;

use App\Models\Packer;
use App\Models\Producer;
use App\Models\Size;
use App\Models\User;
use App\Models\Variety;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Genera volumen para pruebas de rendimiento (punto 160 del pliego):
 *   php artisan galpon:seed-performance --crates=100000 --pallets=10000
 *
 * Inserta en bloques con SQL directo (sin eventos ni auditoría) y SÓLO en
 * entornos que no sean producción. Requiere DemoSeeder previo (catálogos).
 */
class SeedPerformanceData extends Command
{
    protected $signature = 'galpon:seed-performance
        {--crates=100000 : Cantidad de cajones a generar}
        {--pallets=10000 : Cantidad de pallets}
        {--days=365 : Distribuir los datos en los últimos N días}';

    protected $description = 'Genera datos masivos simulados para medir el rendimiento (nunca en producción)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no puede ejecutarse en producción.');

            return self::FAILURE;
        }

        $crates = max(1, (int) $this->option('crates'));
        $pallets = max(1, min((int) $this->option('pallets'), $crates));
        $days = max(1, (int) $this->option('days'));

        $warehouse = Warehouse::query()->value('id');
        $varieties = Variety::query()->pluck('id')->all();
        $sizes = Size::query()->pluck('id')->all();
        $packers = Packer::query()->pluck('id')->all();
        $producers = Producer::query()->pluck('id')->all();
        $user = User::query()->value('id');

        if (! $warehouse || ! $varieties || ! $sizes || ! $packers || ! $producers || ! $user) {
            $this->error('Faltan catálogos. Ejecutá primero: php artisan db:seed --class=DemoSeeder');

            return self::FAILURE;
        }

        $prefix = 'PERF'.strtoupper(Str::random(3)).'-';
        $this->info("Generando {$pallets} pallets y {$crates} cajones (prefijo {$prefix})...");
        $started = microtime(true);

        $palletIds = [];
        foreach (array_chunk(range(1, $pallets), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $at = now()->subMinutes(random_int(0, $days * 1440));
                $rows[] = [
                    'warehouse_id' => $warehouse, 'code' => $prefix.'P'.$i, 'producer_id' => $producers[array_rand($producers)],
                    'variety_id' => $varieties[array_rand($varieties)], 'received_at' => $at, 'quantity' => 0,
                    'status' => 'with_product', 'version' => 0, 'created_by' => $user, 'created_at' => $at, 'updated_at' => $at,
                ];
            }
            DB::table('pallets')->insert($rows);
        }
        $palletIds = DB::table('pallets')->where('code', 'like', $prefix.'P%')->pluck('id')->all();

        $bar = $this->output->createProgressBar($crates);
        $chunkSize = 1000;
        for ($offset = 0; $offset < $crates; $offset += $chunkSize) {
            $count = min($chunkSize, $crates - $offset);
            DB::transaction(function () use ($count, $offset, $prefix, $warehouse, $palletIds, $varieties, $sizes, $packers, $producers, $user, $days) {
                $rows = [];
                for ($i = 0; $i < $count; $i++) {
                    $at = now()->subMinutes(random_int(0, $days * 1440));
                    $rows[] = [
                        'warehouse_id' => $warehouse, 'code' => $prefix.'C'.($offset + $i + 1), 'pallet_id' => $palletIds[array_rand($palletIds)],
                        'producer_id' => $producers[array_rand($producers)], 'variety_id' => $varieties[array_rand($varieties)],
                        'size_id' => $sizes[array_rand($sizes)], 'packer_id' => $packers[array_rand($packers)],
                        'weight' => random_int(1600, 2050) / 100, 'status' => 'processed', 'quality_status' => 'pending',
                        'processed_at' => $at, 'processed_by' => $user, 'version' => 0, 'created_by' => $user,
                        'created_at' => $at, 'updated_at' => $at,
                    ];
                }
                $lastId = (int) DB::table('crates')->max('id');
                DB::table('crates')->insert($rows);

                $inserted = DB::table('crates')
                    ->where('id', '>', $lastId)
                    ->where('code', 'like', $prefix.'C%')
                    ->get(['id', 'packer_id', 'variety_id', 'size_id', 'weight', 'processed_at']);
                $records = [];
                foreach ($inserted as $crate) {
                    $records[] = [
                        'warehouse_id' => $warehouse, 'crate_id' => $crate->id, 'packer_id' => $crate->packer_id,
                        'variety_id' => $crate->variety_id, 'size_id' => $crate->size_id, 'weight' => $crate->weight,
                        'weight_source' => 'manual', 'user_id' => $user, 'recorded_at' => $crate->processed_at,
                        'created_at' => $crate->processed_at, 'updated_at' => $crate->processed_at,
                    ];
                }
                foreach (array_chunk($records, 500) as $part) {
                    DB::table('production_records')->insert($part);
                }
            });
            $bar->advance($count);
        }
        $bar->finish();
        $this->newLine();

        $this->info(sprintf('Listo en %.1f s. Probá: listado de cajones, reportes y dashboard con estos volúmenes.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
