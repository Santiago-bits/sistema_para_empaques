<?php

namespace App\Services;

use App\Models\Packer;
use App\Models\ProductionRecord;
use Illuminate\Support\Carbon;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class PackerService
{
    /**
     * Resumen de producción válida (registros no anulados) del embalador.
     *
     * @return array{today: array{crates: int, kg: float}, month: array{crates: int, kg: float}, days: list<array{date: Carbon, crates: int, kg: float}>}
     */
    public function productionSummary(Packer $packer, ?Carbon $at = null): array
    {
        $at ??= now();
        $base = fn () => ProductionRecord::query()->where('packer_id', $packer->id)->whereNull('voided_at');
        $totals = function (Carbon $from, Carbon $to) use ($base) {
            $row = $base()->whereBetween('recorded_at', [$from, $to])
                ->selectRaw('COUNT(*) as crates, COALESCE(SUM(weight), 0) as kg')->first();

            return ['crates' => (int) $row->crates, 'kg' => (float) $row->kg];
        };

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $at->copy()->subDays($i);
            $days[] = ['date' => $day->copy()->startOfDay()] + $totals($day->copy()->startOfDay(), $day->copy()->endOfDay());
        }

        return [
            'today' => $totals($at->copy()->startOfDay(), $at->copy()->endOfDay()),
            'month' => $totals($at->copy()->startOfMonth(), $at->copy()->endOfMonth()),
            'days' => $days,
        ];
    }

    /** Código de barras Code128 en SVG (inline, sin encabezado XML) para la credencial. */
    public function barcodeSvg(string $code, float $width = 260, float $height = 60): string
    {
        $barcode = (new TypeCode128)->getBarcode($code);

        return (new SvgRenderer)->setSvgType(SvgRenderer::TYPE_SVG_INLINE)->render($barcode, $width, $height);
    }
}
