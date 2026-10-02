<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LoadStatus;
use App\Models\Crate;
use App\Models\Invoice;
use App\Models\Load;
use App\Models\Pallet;
use App\Models\Remito;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Portal de cliente / propietario. TODA consulta pasa por estos scopes: un propietario ve
 * sólo su mercadería (owner_id) y un cliente sólo sus cargas, remitos y facturas (client_id).
 * Sin vínculo no se ve nada (nunca "todo").
 */
class PortalService
{
    public function isLinked(User $user): bool
    {
        return $user->owner_id !== null || $user->client_id !== null;
    }

    public function crates(User $user): Builder
    {
        return Crate::query()->where('owner_id', $this->ownerId($user));
    }

    public function pallets(User $user): Builder
    {
        return Pallet::query()->where('owner_id', $this->ownerId($user));
    }

    /** Cargas del cliente o con mercadería del propietario (excluye borradores internos). */
    public function loads(User $user): Builder
    {
        return Load::query()
            ->where(fn ($q) => $q->when($user->client_id, fn ($w) => $w->where('client_id', $user->client_id))
                ->when($user->owner_id, fn ($w) => $w->orWhere('owner_id', $user->owner_id))
                ->when(! $this->isLinked($user), fn ($w) => $w->whereRaw('1 = 0')))
            ->whereIn('status', [LoadStatus::Closed->value, LoadStatus::Dispatched->value, LoadStatus::Delivered->value]);
    }

    public function remitos(User $user): Builder
    {
        return Remito::query()->where('client_id', $this->clientId($user));
    }

    /** Sólo comprobantes autorizados por ARCA: borradores y rechazados son internos. */
    public function invoices(User $user): Builder
    {
        return Invoice::query()->where('client_id', $this->clientId($user))->where('status', InvoiceStatus::Authorized->value);
    }

    public function summary(User $user): array
    {
        $inStock = [CrateStatus::Processed->value, CrateStatus::InControl->value, CrateStatus::Approved->value, CrateStatus::Reserved->value];
        $season = \App\Models\Season::current();

        return [
            'crates_in_stock' => $user->owner_id ? $this->crates($user)->whereIn('status', $inStock)->count() : null,
            'kg_in_stock' => $user->owner_id ? (float) $this->crates($user)->whereIn('status', $inStock)->sum('weight') : null,
            'crates_season' => $user->owner_id ? $this->crates($user)->when($season, fn ($q) => $q->where('season_id', $season->id))->count() : null,
            'pallets_season' => $user->owner_id ? $this->pallets($user)->when($season, fn ($q) => $q->where('season_id', $season->id))->count() : null,
            'loads_dispatched' => $this->loads($user)->whereIn('status', [LoadStatus::Dispatched->value, LoadStatus::Delivered->value])->count(),
            'invoiced' => $user->client_id ? (float) $this->invoices($user)->sum('total_amount') : null,
        ];
    }

    /** -1 nunca coincide con un id real: sin vínculo la consulta queda vacía. */
    private function ownerId(User $user): int
    {
        return $user->owner_id ?? -1;
    }

    private function clientId(User $user): int
    {
        return $user->client_id ?? -1;
    }
}
