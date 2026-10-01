<?php

namespace App\Services;

use App\Enums\CrateStatus;
use App\Enums\PalletStatus;
use App\Models\AuditLog;
use App\Models\Crate;
use App\Models\Load;
use App\Models\LocationMovement;
use App\Models\Pallet;
use App\Models\ProductionRecord;
use App\Models\QualityControl;
use App\Models\StateHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Ficha de trazabilidad: cadena PRODUCTOR → LOTE → PALLET → CAJÓN → EMBALADOR →
 * VARIEDAD → TAMAÑO → PESO → CALIDAD → UBICACIÓN → CARGA → CAMIÓN → DESTINO →
 * REMITO → FACTURA, más una línea de tiempo unificada (QUÉ / QUIÉN / CUÁNDO /
 * DÓNDE / CUÁNTO / POR QUÉ).
 *
 * Remitos, facturas y asignaciones a cargas se leen con consultas directas para no
 * depender de la forma en que el módulo de logística modele esas relaciones.
 */
class TraceabilityService
{
    /** Busca por código o código de barras: primero cajón, luego pallet. */
    public function find(string $code): Crate|Pallet|null
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return Crate::query()->where(fn ($q) => $q->where('code', $code)->orWhere('barcode', $code))->first()
            ?? Pallet::query()->where(fn ($q) => $q->where('code', $code)->orWhere('barcode', $code))->first();
    }

    /**
     * @return array{crate: Crate, chain: list<array>, timeline: list<array>, loads: list<array>}
     */
    public function forCrate(Crate $crate): array
    {
        $crate->loadMissing([
            'pallet.producer', 'pallet.location', 'lot.producer', 'lot.owner', 'producer', 'owner', 'variety', 'size',
            'packer', 'shift', 'productionLine', 'location', 'processor',
        ]);

        $assignments = DB::table('load_crates')->where('crate_id', $crate->id)->orderBy('added_at')->orderBy('id')->get();
        $loadIds = $assignments->pluck('load_id')->push($crate->current_load_id)->filter()->unique()->values();
        $loads = $loadIds->isEmpty() ? collect() : Load::query()->with('truck', 'destination', 'client', 'driver')
            ->whereIn('id', $loadIds)->get()->keyBy('id');

        $activeAssignment = $assignments->whereNull('removed_at')->last();
        $currentLoadId = $crate->current_load_id ?? $activeAssignment?->load_id ?? $assignments->last()?->load_id;
        $load = $currentLoadId ? $loads->get($currentLoadId) : null;

        $remito = $load ? DB::table('remitos')->where('load_id', $load->id)->whereNull('deleted_at')->first() : null;
        $invoices = $load ? DB::table('invoices')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('load_id', $load->id)->when($remito, fn ($w) => $w->orWhere('remito_id', $remito->id)))
            ->orderBy('issued_on')->get() : collect();

        $lastControl = QualityControl::query()->where('crate_id', $crate->id)->latest('controlled_at')->first();
        $producer = $crate->producer ?? $crate->lot?->producer ?? $crate->pallet?->producer;
        $owner = $crate->owner ?? $crate->lot?->owner;
        $location = $crate->location ?? $crate->pallet?->location;

        $chain = [
            $this->node('producer', 'Productor', $producer?->name, $producer?->code),
            $this->node('owner', 'Propietario', $owner?->name, $owner?->code),
            $this->node('lot', 'Lote', $crate->lot?->code, $crate->lot ? fdate($crate->lot->date) : null, $this->url('lots.show', $crate->lot)),
            $this->node('pallet', 'Pallet', $crate->pallet?->code, $crate->pallet ? 'Ingreso '.fdate($crate->pallet->received_at, true) : null,
                $this->url('pallets.show', $crate->pallet)),
            $this->node('crate', 'Cajón', $crate->code, $crate->status->label(), $this->url('crates.show', $crate)),
            $this->node('packer', 'Embalador', $crate->packer ? $crate->packer->code.' — '.$crate->packer->full_name : null),
            $this->node('variety', 'Variedad', $crate->variety?->name),
            $this->node('size', 'Tamaño', $crate->size?->name),
            $this->node('weight', 'Peso', $crate->weight !== null ? kg($crate->weight) : null),
            $this->node('quality', 'Calidad', \App\Models\Crate::QUALITY_STATUSES[$crate->quality_status] ?? $crate->quality_status,
                $lastControl ? 'Control '.fdate($lastControl->controlled_at, true) : null),
            $this->node('location', 'Ubicación', $location?->path()),
            $this->node('load', 'Carga', $load?->number, $load ? $load->status->label() : null, $this->url('loads.show', $load)),
            $this->node('truck', 'Camión', $load?->truck?->plate, $load?->driver ? trim($load->driver->first_name.' '.$load->driver->last_name) : null),
            $this->node('destination', 'Destino', $load?->destination?->name, $load?->client?->business_name),
            $this->node('remito', 'Remito', $remito?->number, $remito ? fdate(Carbon::parse($remito->issued_at), true) : null,
                $remito ? $this->routeUrl('remitos.show', $remito->id) : null),
            $this->node('invoice', 'Factura', $invoices->isEmpty() ? null : $invoices->map(fn ($i) => $this->invoiceNumber($i))->implode(', '),
                $invoices->isEmpty() ? null : $invoices->pluck('status')->unique()->implode(', '),
                $invoices->count() === 1 ? $this->routeUrl('invoices.show', $invoices->first()->id) : null),
        ];

        return [
            'crate' => $crate,
            'chain' => $chain,
            'timeline' => $this->crateTimeline($crate, $assignments, $loads),
            'loads' => $assignments->map(fn ($a) => [
                'load' => $loads->get($a->load_id),
                'added_at' => Carbon::parse($a->added_at),
                'removed_at' => $a->removed_at ? Carbon::parse($a->removed_at) : null,
            ])->all(),
        ];
    }

    /**
     * @return array{pallet: Pallet, chain: list<array>, timeline: list<array>, summary: array}
     */
    public function forPallet(Pallet $pallet): array
    {
        $pallet->loadMissing('lot', 'producer', 'owner', 'variety', 'location', 'creator');

        $summary = Crate::query()->where('pallet_id', $pallet->id)
            ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as voided', [CrateStatus::Voided->value])
            ->first();

        $chain = [
            $this->node('producer', 'Productor', $pallet->producer?->name, $pallet->producer?->code),
            $this->node('owner', 'Propietario', $pallet->owner?->name, $pallet->owner?->code),
            $this->node('lot', 'Lote', $pallet->lot?->code, $pallet->lot ? fdate($pallet->lot->date) : null, $this->url('lots.show', $pallet->lot)),
            $this->node('pallet', 'Pallet', $pallet->code, $pallet->status->label(), $this->url('pallets.show', $pallet)),
            $this->node('variety', 'Variedad', $pallet->variety?->name),
            $this->node('weight', 'Peso bruto', $pallet->gross_weight !== null ? kg($pallet->gross_weight) : null),
            $this->node('location', 'Ubicación', $pallet->location?->path()),
            $this->node('crates', 'Cajones', num($summary->crates ?? 0), kg($summary->kg ?? 0)),
        ];

        $events = collect();
        $this->stateEvents($events, $pallet->getMorphClass(), $pallet->id, PalletStatus::class);
        $this->movementEvents($events, $pallet->getMorphClass(), $pallet->id);
        $this->auditEvents($events, $pallet->getMorphClass(), $pallet->id);

        return [
            'pallet' => $pallet,
            'chain' => $chain,
            'timeline' => $this->sort($events),
            'summary' => ['crates' => (int) ($summary->crates ?? 0), 'kg' => (float) ($summary->kg ?? 0), 'voided' => (int) ($summary->voided ?? 0)],
        ];
    }

    private function crateTimeline(Crate $crate, $assignments, $loads): array
    {
        $events = collect();
        $type = $crate->getMorphClass();

        $this->stateEvents($events, $type, $crate->id, CrateStatus::class);

        ProductionRecord::query()->with('packer', 'variety', 'size', 'user', 'authorizer', 'productionLine', 'shift', 'voider')
            ->where('crate_id', $crate->id)->orderBy('recorded_at')->get()
            ->each(function (ProductionRecord $r) use ($events) {
                $events->push($this->event(
                    $r->recorded_at,
                    'Registro de producción'.($r->voided_at ? ' (anulado)' : ''),
                    $r->user?->full_name,
                    trim(implode(' · ', array_filter([$r->productionLine?->name, $r->shift ? 'Turno '.$r->shift->name : null]))),
                    kg($r->weight).($r->weight_source === 'scale' ? ' (balanza)' : ''),
                    $r->authorized_by ? 'Peso autorizado por '.$r->authorizer?->full_name.': '.$r->authorization_reason : null,
                    implode(' · ', array_filter([
                        $r->packer ? 'Embalador '.$r->packer->code.' — '.$r->packer->full_name : null,
                        $r->variety?->name, $r->size?->name,
                    ])),
                    $r->voided_at ? 'red' : 'brand',
                ));
                if ($r->voided_at) {
                    $events->push($this->event($r->voided_at, 'Anulación del registro de producción', $r->voider?->full_name,
                        null, kg($r->weight), $r->void_reason, null, 'red'));
                }
            });

        QualityControl::query()->with('user')->where('crate_id', $crate->id)->orderBy('controlled_at')->get()
            ->each(fn (QualityControl $q) => $events->push($this->event(
                $q->controlled_at,
                'Control de calidad: '.(QualityControl::RESULTS[$q->result] ?? $q->result),
                $q->user?->full_name,
                null,
                'Daño '.pct($q->damage_pct).' · Podredumbre '.pct($q->rot_pct).' · Descarte '.pct($q->reject_pct),
                $q->notes,
                $q->defects,
                $q->result === 'rejected' ? 'red' : ($q->result === 'observed' ? 'amber' : 'brand'),
            )));

        $this->movementEvents($events, $type, $crate->id);

        $users = User::query()->whereIn('id', $assignments->pluck('added_by')->merge($assignments->pluck('removed_by'))->filter()->unique())
            ->get()->keyBy('id');
        foreach ($assignments as $a) {
            $load = $loads->get($a->load_id);
            $events->push($this->event(Carbon::parse($a->added_at), 'Asignado a la carga '.($load?->number ?? '#'.$a->load_id),
                $users->get($a->added_by)?->full_name, $load?->truck ? 'Camión '.$load->truck->plate : null, null, null,
                $load?->destination?->name, 'violet'));
            if ($a->removed_at) {
                $events->push($this->event(Carbon::parse($a->removed_at), 'Quitado de la carga '.($load?->number ?? '#'.$a->load_id),
                    $users->get($a->removed_by)?->full_name, null, null, null, null, 'amber'));
            }
        }

        $this->auditEvents($events, $type, $crate->id);

        return $this->sort($events);
    }

    private function stateEvents($events, string $type, int $id, string $enum): void
    {
        StateHistory::query()->with('user')->where('stateful_type', $type)->where('stateful_id', $id)
            ->orderBy('created_at')->orderBy('id')->get()
            ->each(function (StateHistory $h) use ($events, $enum) {
                $from = $h->from_state ? ($enum::tryFrom($h->from_state)?->label() ?? $h->from_state) : null;
                $to = $enum::tryFrom($h->to_state)?->label() ?? $h->to_state;
                $events->push($this->event($h->created_at, $from ? "Estado: {$from} → {$to}" : "Alta: {$to}",
                    $h->user?->full_name, null, null, $h->notes, null, $h->to_state === 'voided' ? 'red' : 'sky'));
            });
    }

    private function movementEvents($events, string $type, int $id): void
    {
        LocationMovement::query()->with('fromLocation', 'toLocation', 'user')->where('movable_type', $type)->where('movable_id', $id)
            ->orderBy('moved_at')->get()
            ->each(fn (LocationMovement $m) => $events->push($this->event(
                $m->moved_at,
                'Movimiento de ubicación',
                $m->user?->full_name,
                trim(($m->fromLocation?->name ?? '—').' → '.($m->toLocation?->name ?? $m->to_label ?? '—')),
                null,
                $m->notes,
                null,
                'stone',
            )));
    }

    /** Auditoría: cambios de datos (los cambios de estado ya figuran en el historial). */
    private function auditEvents($events, string $type, int $id): void
    {
        $labels = ['update' => 'Modificación de datos', 'soft_delete' => 'Eliminación', 'restore' => 'Restauración',
            'force_status' => 'Cambio de estado forzado', 'authorize_weight' => 'Autorización de peso', 'print_label' => 'Impresión de etiqueta'];

        AuditLog::query()->with('user')->where('auditable_type', $type)->where('auditable_id', $id)
            ->whereIn('action', array_keys($labels))->orderBy('created_at')->orderBy('id')->get()
            ->each(function (AuditLog $log) use ($events, $labels) {
                $changes = $log->action !== 'update' ? '' : collect($log->new_values ?? [])->map(function ($new, $field) use ($log) {
                    $old = $log->old_values[$field] ?? null;

                    return field_label($field).': '.($old ?? '—').' → '.(is_scalar($new) ? $new : json_encode($new));
                })->implode(' · ');
                $events->push($this->event($log->created_at, $labels[$log->action] ?? $log->action, $log->user?->full_name,
                    $log->ip_address ? 'IP '.$log->ip_address : null, null, $log->reason, $changes ?: $log->description, 'amber'));
            });
    }

    private function event(?\DateTimeInterface $at, string $what, ?string $who, ?string $where, ?string $howMuch, ?string $why, ?string $detail, string $color): array
    {
        return [
            'time' => $at,
            'title' => $what,
            'user' => $who,
            'where' => $where ?: null,
            'amount' => $howMuch,
            'reason' => $why ?: null,
            'detail' => $detail ?: null,
            'color' => $color,
        ];
    }

    private function sort($events): array
    {
        return $events->sortBy(fn ($e) => $e['time']?->format('Y-m-d H:i:s.u') ?? '')->values()->all();
    }

    private function node(string $key, string $label, ?string $value, ?string $hint = null, ?string $url = null): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'hint' => $hint, 'url' => $value ? $url : null];
    }

    private function url(string $route, $model): ?string
    {
        return $model ? $this->routeUrl($route, $model) : null;
    }

    private function routeUrl(string $route, $param): ?string
    {
        return Route::has($route) ? route($route, $param) : null;
    }

    private function invoiceNumber(object $invoice): string
    {
        if (! $invoice->number) {
            return 'Borrador #'.$invoice->id;
        }

        return str_pad((string) $invoice->point_of_sale, 5, '0', STR_PAD_LEFT).'-'.str_pad((string) $invoice->number, 8, '0', STR_PAD_LEFT);
    }
}
