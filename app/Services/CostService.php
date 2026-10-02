<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Cost;
use App\Models\Invoice;
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
    /** Códigos ARCA de notas de crédito A, B y C. */
    private const CREDIT_NOTES = [3, 8, 13];

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

    public function profitability(CarbonInterface $from, CarbonInterface $to): array
    {
        $invoices = Invoice::query()->where('status', InvoiceStatus::Authorized->value)
            ->whereDate('issued_on', '>=', $from->toDateString())->whereDate('issued_on', '<=', $to->toDateString())
            ->get(['voucher_type', 'total_amount', 'exchange_rate']);

        $revenue = round($invoices->sum(fn (Invoice $i) => (float) $i->total_amount * (float) ($i->exchange_rate ?: 1)
            * (in_array((int) $i->voucher_type, self::CREDIT_NOTES, true) ? -1 : 1)), 2);
        $costs = round((float) Cost::query()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->sum('amount'), 2);
        $kg = (float) ($this->reports->indicators(ReportFilters::between($from, $to))['kg_processed'] ?? 0);

        return [
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => round($revenue - $costs, 2),
            'margin_pct' => $revenue > 0 ? round(($revenue - $costs) / $revenue * 100, 1) : null,
            'kg' => $kg,
            'cost_per_kg' => $kg > 0 ? round($costs / $kg, 2) : null,
            'revenue_per_kg' => $kg > 0 ? round($revenue / $kg, 2) : null,
            'invoices' => $invoices->count(),
        ];
    }
}
