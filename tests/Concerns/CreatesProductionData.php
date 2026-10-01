<?php

namespace Tests\Concerns;

use App\Enums\CrateStatus;
use App\Models\Crate;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Pallet;
use App\Models\Producer;
use App\Models\Size;
use App\Models\Variety;

/** Datos mínimos de producción para tests (sin depender de factories). */
trait CreatesProductionData
{
    protected function variety(string $code = 'NAR', string $name = 'Naranja'): Variety
    {
        return Variety::query()->firstOrCreate(['code' => $code], ['name' => $name]);
    }

    protected function makeSize(string $code = '70'): Size
    {
        return Size::query()->firstOrCreate(['code' => $code], ['name' => 'Tamaño '.$code]);
    }

    protected function packer(string $code = 'EMB001', bool $active = true): Packer
    {
        return Packer::query()->firstOrCreate(['code' => $code], ['first_name' => 'Juan', 'last_name' => 'Pérez', 'active' => $active]);
    }

    protected function producer(string $code = 'PROD-1'): Producer
    {
        return Producer::query()->firstOrCreate(['code' => $code], ['name' => 'Finca '.$code]);
    }

    protected function lot(string $code = 'LOT-1'): Lot
    {
        return Lot::query()->firstOrCreate(['code' => $code], [
            'date' => today(), 'producer_id' => $this->producer()->id, 'status' => 'open',
        ]);
    }

    protected function pallet(string $code = 'PAL-1', string $status = 'received'): Pallet
    {
        return Pallet::query()->firstOrCreate(['code' => $code], [
            'received_at' => now(), 'producer_id' => $this->producer()->id, 'lot_id' => $this->lot()->id, 'status' => $status,
        ]);
    }

    /** Cajón en el estado indicado; si está procesado o más, con variedad/tamaño/embalador/peso. */
    protected function crate(string $code = 'CJ-1', CrateStatus|string $status = CrateStatus::Registered, array $attributes = []): Crate
    {
        $status = $status instanceof CrateStatus ? $status : CrateStatus::from($status);
        $processed = $status !== CrateStatus::Registered;

        return Crate::query()->create(array_merge([
            'code' => $code,
            'status' => $status,
            'quality_status' => $status === CrateStatus::Approved ? 'approved' : ($status === CrateStatus::Rejected ? 'rejected' : 'pending'),
            'lot_id' => $this->lot()->id,
            'producer_id' => $this->producer()->id,
            'variety_id' => $processed ? $this->variety()->id : null,
            'size_id' => $processed ? $this->makeSize()->id : null,
            'packer_id' => $processed ? $this->packer()->id : null,
            'weight' => $processed ? 18.5 : null,
            'processed_at' => $processed ? now() : null,
        ], $attributes));
    }

    /** Payload válido para el modo escaneo. */
    protected function scanPayload(array $overrides = []): array
    {
        return array_merge([
            'crate_code' => 'CJ-100',
            'packer_code' => $this->packer()->code,
            'weight' => '18,50',
            'variety_id' => $this->variety()->id,
            'size_id' => $this->makeSize()->id,
        ], $overrides);
    }
}
