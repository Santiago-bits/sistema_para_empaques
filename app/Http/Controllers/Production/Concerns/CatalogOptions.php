<?php

namespace App\Http\Controllers\Production\Concerns;

use App\Models\Lot;
use App\Models\Owner;
use App\Models\Packer;
use App\Models\Producer;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\Size;
use App\Models\Variety;
use App\Models\WarehouseLocation;
use Illuminate\Support\Collection;

/** Opciones [id => etiqueta] para selects de pallets, cajones y producción. */
trait CatalogOptions
{
    protected function varietyOptions(bool $onlyActive = true): Collection
    {
        return Variety::query()->when($onlyActive, fn ($q) => $q->where('active', true))->orderBy('name')->pluck('name', 'id');
    }

    protected function sizeOptions(bool $onlyActive = true): Collection
    {
        return Size::query()->when($onlyActive, fn ($q) => $q->where('active', true))->orderBy('sort')->orderBy('name')->pluck('name', 'id');
    }

    protected function packerOptions(bool $onlyActive = false): Collection
    {
        return Packer::query()->when($onlyActive, fn ($q) => $q->where('active', true))->orderBy('code')->get()
            ->mapWithKeys(fn (Packer $p) => [$p->id => $p->code.' — '.$p->full_name]);
    }

    protected function producerOptions(): Collection
    {
        return Producer::query()->orderBy('name')->pluck('name', 'id');
    }

    protected function ownerOptions(): Collection
    {
        return Owner::query()->orderBy('name')->pluck('name', 'id');
    }

    protected function lotOptions(bool $onlyOpen = false): Collection
    {
        return Lot::query()->when($onlyOpen, fn ($q) => $q->where('status', 'open'))
            ->with('producer:id,name')->latest('date')->limit(300)->get()
            ->mapWithKeys(fn (Lot $l) => [$l->id => $l->code.($l->producer ? ' — '.$l->producer->name : '')]);
    }

    protected function locationOptions(): Collection
    {
        return WarehouseLocation::query()->where('active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (WarehouseLocation $l) => [$l->id => $l->code.' — '.$l->name]);
    }

    protected function lineOptions(): Collection
    {
        return ProductionLine::query()->where('active', true)->orderBy('code')->pluck('name', 'id');
    }

    protected function shiftOptions(): Collection
    {
        return Shift::query()->orderBy('starts_at')->pluck('name', 'id');
    }
}
