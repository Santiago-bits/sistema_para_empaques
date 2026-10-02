<?php

namespace App\Services;

use App\Models\Cost;
use App\Models\Load;
use App\Models\User;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Costos operativos (en pesos) y rentabilidad del período:
 * ingresos = comprobantes AUTORIZADOS convertidos a pesos con su cotización (notas de crédito restan).
 */
class CostService
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function save(array $data, User $by, ?Cost $cost = null): Cost
    {
        $attributes = [
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'currency' => 'ARS',
            'date' => $data['date'],
            'costable_type' => ! empty($data['load_id']) ? (new Load)->getMorphClass() : null,
            'costable_id' => $data['load_id'] ?? null,
        ];

        if ($cost) {
            $cost->update($attributes);

            return $cost;
        }

        return Cost::query()->create($attributes + ['user_id' => $by->id]);
    }

    public function filtered(array $filters): Builder
    {
        return Cost::query()
            ->whereDate('date', '>=', $filters['from']->toDateString())->whereDate('date', '<=', $filters['to']->toDateString())
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('description', 'like', '%'.addcslashes($t, '%_\\').'%'));
    }

    /** @return array<string, float> categoría => total */
    public function totalsByCategory(Builder $query): array
    {
        return (clone $query)->reorder()->selectRaw('category, SUM(amount) as total')->groupBy('category')
            ->pluck('total', 'category')->map(fn ($v) => round((float) $v, 2))->all();
    }

    /**
     * Rentabilidad del período. Los ingresos salen de ReportService::billingTotals (misma cifra que
     * en Reportes): NETO sin IVA, en pesos según la cotización, con las notas de crédito restando y
     * sólo comprobantes autorizados del modo ARCA vigente.
     */
    public function profitability(CarbonInterface $from, CarbonInterface $to): array
    {
        $filters = ReportFilters::between($from, $to);
        $billing = $this->reports->billingTotals($filters);
        $revenue = $billing['net'];
        $costs = round((float) Cost::query()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->sum('amount'), 2);
        $kg = (float) ($this->reports->indicators($filters)['kg_processed'] ?? 0);

        return [
            'revenue' => $revenue,
            'revenue_total' => $billing['total'],
            'costs' => $costs,
            'profit' => round($revenue - $costs, 2),
            'margin_pct' => $revenue > 0 ? round(($revenue - $costs) / $revenue * 100, 1) : null,
            'kg' => $kg,
            'cost_per_kg' => $kg > 0 ? round($costs / $kg, 2) : null,
            'revenue_per_kg' => $kg > 0 ? round($revenue / $kg, 2) : null,
            'invoices' => $billing['count'],
        ];
    }
}
