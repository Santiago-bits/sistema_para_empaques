<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Crate;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\Lot;
use App\Models\Packer;
use App\Models\Pallet;
use App\Models\Producer;
use App\Models\Remito;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Búsqueda global (barra superior): cajón, pallet, lote, carga, remito, factura, productor,
 * cliente, patente, chofer, embalador y usuario. Cada grupo respeta su permiso y su módulo
 * (Gate::before devuelve false si el módulo está desactivado).
 */
class SearchService
{
    public const MIN_LENGTH = 2;

    private const PER_GROUP = 8;

    /**
     * Si el término coincide exactamente con un código único (lo típico al escanear),
     * devuelve la URL para ir directo.
     */
    public function exactMatch(User $user, string $term): ?string
    {
        $code = mb_strtoupper(trim($term));
        $candidates = [
            ['crates.view', fn () => Crate::query()->where('code', $code)->orWhere('barcode', $code)->first(), 'crates.show'],
            ['pallets.view', fn () => Pallet::query()->where('code', $code)->orWhere('barcode', $code)->first(), 'pallets.show'],
            ['lots.view', fn () => Lot::query()->where('code', $code)->first(), 'lots.show'],
            ['loads.view', fn () => Load::query()->where('number', $code)->first(), 'loads.show'],
            ['remitos.view', fn () => Remito::query()->where('number', $code)->first(), 'remitos.show'],
        ];

        foreach ($candidates as [$permission, $find, $route]) {
            if ($user->can($permission) && ($model = $find())) {
                return route($route, $model);
            }
        }

        return null;
    }

    /** @return list<array{key: string, label: string, icon: string, items: list<array{title: string, subtitle: ?string, url: string}>}> */
    public function search(User $user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $term);
        $results = [];

        $plate = '%'.addcslashes(preg_replace('/[\s\-]/', '', $term), '%_\\').'%';

        // Cajones y pallets: búsqueda por prefijo del código (usa el índice; son las tablas más grandes).
        $prefix = addcslashes(mb_strtoupper($term), '%_\\').'%';

        foreach ($this->sources($like, $digits, $plate, $prefix) as $key => $source) {
            if (! $user->can($source['permission'])) {
                continue;
            }
            $items = $source['query']()->limit(self::PER_GROUP)->get()->map($source['map'])->all();
            if ($items) {
                $results[] = ['key' => $key, 'label' => $source['label'], 'icon' => $source['icon'], 'items' => $items];
            }
        }

        return $results;
    }

    private function sources(string $like, string $digits, string $plate, string $prefix): array
    {
        $byDigits = fn (Builder $q, string $column) => strlen($digits) >= 5 ? $q->orWhere($column, 'like', '%'.$digits.'%') : $q;

        return [
            'crates' => [
                'label' => 'Cajones', 'icon' => 'box', 'permission' => 'crates.view',
                'query' => fn () => Crate::query()->with('variety:id,name', 'packer:id,code')
                    ->where(fn ($q) => $q->where('code', 'like', $prefix)->orWhere('barcode', 'like', $prefix))->latest('id'),
                'map' => fn (Crate $c) => ['title' => $c->code, 'subtitle' => trim(($c->variety?->name ?? '').' · '.$c->status->label().($c->weight ? ' · '.kg($c->weight) : ''), ' ·'), 'url' => route('crates.show', $c)],
            ],
            'pallets' => [
                'label' => 'Pallets', 'icon' => 'pallet', 'permission' => 'pallets.view',
                'query' => fn () => Pallet::query()->with('producer:id,name')
                    ->where(fn ($q) => $q->where('code', 'like', $prefix)->orWhere('barcode', 'like', $prefix))->latest('id'),
                'map' => fn (Pallet $p) => ['title' => $p->code, 'subtitle' => trim(($p->producer?->name ?? '').' · '.$p->status->label(), ' ·'), 'url' => route('pallets.show', $p)],
            ],
            'lots' => [
                'label' => 'Lotes', 'icon' => 'layers', 'permission' => 'lots.view',
                'query' => fn () => Lot::query()->with('producer:id,name')->where('code', 'like', $like)->latest('id'),
                'map' => fn (Lot $l) => ['title' => $l->code, 'subtitle' => $l->producer?->name, 'url' => route('lots.show', $l)],
            ],
            'loads' => [
                'label' => 'Cargas', 'icon' => 'truck', 'permission' => 'loads.view',
                'query' => fn () => Load::query()->with('client:id,business_name', 'truck:id,plate')
                    ->where(fn ($q) => $q->where('number', 'like', $like)
                        ->orWhereHas('truck', fn ($t) => $t->where('plate', 'like', $like)))->latest('id'),
                'map' => fn (Load $l) => ['title' => $l->number, 'subtitle' => trim(($l->client?->business_name ?? '').' · '.($l->truck?->plate ?? '').' · '.$l->status->label(), ' ·'), 'url' => route('loads.show', $l)],
            ],
            'remitos' => [
                'label' => 'Remitos', 'icon' => 'document', 'permission' => 'remitos.view',
                'query' => fn () => Remito::query()->with('client:id,business_name')->where('number', 'like', $like)->latest('id'),
                'map' => fn (Remito $r) => ['title' => $r->number, 'subtitle' => $r->client?->business_name, 'url' => route('remitos.show', $r)],
            ],
            'invoices' => [
                'label' => 'Facturas', 'icon' => 'receipt', 'permission' => 'billing.view',
                'query' => fn () => Invoice::query()->with('client:id,business_name')
                    ->where(fn ($q) => $q->where('cae', 'like', $like)->when($digits !== '', fn ($w) => $w->orWhere('number', (int) substr($digits, -8))))->latest('id'),
                'map' => fn (Invoice $i) => ['title' => $i->formattedNumber(), 'subtitle' => trim(($i->client?->business_name ?? '').' · '.money($i->total_amount), ' ·'), 'url' => route('invoices.show', $i)],
            ],
            'producers' => [
                'label' => 'Productores', 'icon' => 'users', 'permission' => 'catalogs.view',
                'query' => fn () => Producer::query()->where(fn ($q) => $byDigits($q->where('name', 'like', $like)->orWhere('code', 'like', $like), 'cuit'))->orderBy('name'),
                'map' => fn (Producer $p) => ['title' => $p->name, 'subtitle' => $p->cuit ? 'CUIT '.$p->cuit : $p->code, 'url' => route('catalogs.producers.show', $p)],
            ],
            'clients' => [
                'label' => 'Clientes', 'icon' => 'briefcase', 'permission' => 'catalogs.view',
                'query' => fn () => Client::query()->where(fn ($q) => $byDigits($q->where('business_name', 'like', $like)->orWhere('name', 'like', $like), 'cuit'))->orderBy('business_name'),
                'map' => fn (Client $c) => ['title' => $c->business_name, 'subtitle' => $c->cuit ? 'CUIT '.$c->cuit : null, 'url' => route('catalogs.clients.show', $c)],
            ],
            'trucks' => [
                'label' => 'Camiones', 'icon' => 'truck', 'permission' => 'catalogs.view',
                'query' => fn () => Truck::query()->where(fn ($q) => $q->where('plate', 'like', $like)->orWhere('plate', 'like', $plate))->orderBy('plate'),
                'map' => fn (Truck $t) => ['title' => $t->plate, 'subtitle' => trim($t->brand.' '.$t->model) ?: null, 'url' => route('catalogs.trucks.show', $t)],
            ],
            'drivers' => [
                'label' => 'Camioneros', 'icon' => 'users', 'permission' => 'catalogs.view',
                'query' => fn () => Driver::query()->where(fn ($q) => $byDigits($q->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like), 'dni'))->orderBy('last_name'),
                'map' => fn (Driver $d) => ['title' => trim($d->last_name.', '.$d->first_name, ', '), 'subtitle' => $d->dni ? 'DNI '.$d->dni : null, 'url' => route('catalogs.drivers.show', $d)],
            ],
            'packers' => [
                'label' => 'Embaladores', 'icon' => 'users', 'permission' => 'packers.view',
                'query' => fn () => Packer::query()->where(fn ($q) => $byDigits($q->where('code', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('first_name', 'like', $like), 'dni'))->orderBy('last_name'),
                'map' => fn (Packer $p) => ['title' => trim($p->last_name.', '.$p->first_name, ', '), 'subtitle' => $p->code, 'url' => route('packers.show', $p)],
            ],
            'users' => [
                'label' => 'Usuarios', 'icon' => 'shield', 'permission' => 'users.view',
                'query' => fn () => User::query()->visibleTo()->where(fn ($q) => $byDigits($q->where('username', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('first_name', 'like', $like), 'dni'))->orderBy('last_name'),
                'map' => fn (User $u) => ['title' => $u->full_name, 'subtitle' => $u->username, 'url' => route('admin.users.show', $u)],
            ],
        ];
    }
}
