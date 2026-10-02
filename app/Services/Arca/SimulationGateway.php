<?php

namespace App\Services\Arca;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Modo simulación: NO realiza ninguna conexión de red. Asigna el próximo número
 * simulado y un CAE ficticio (comienza con 99, claramente identificable).
 */
class SimulationGateway implements ArcaGateway
{
    public function mode(): string
    {
        return 'simulation';
    }

    public function authorize(Invoice $invoice): ArcaResult
    {
        $number = $this->lastAuthorizedNumber((int) $invoice->point_of_sale, (int) $invoice->voucher_type) + 1;
        $cae = '99'.str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $request = ['PtoVta' => $invoice->point_of_sale, 'CbteTipo' => $invoice->voucher_type, 'CbteDesde' => $number, 'ImpTotal' => (float) $invoice->total_amount];

        return new ArcaResult(
            approved: true,
            number: $number,
            cae: $cae,
            caeExpiresOn: now()->addDays(10)->startOfDay(),
            request: $request,
            response: ['Resultado' => 'A', 'CAE' => $cae, 'simulado' => true],
        );
    }

    public function lastAuthorizedNumber(int $pointOfSale, int $voucherType): int
    {
        return (int) DB::table('invoices')->where('arca_mode', 'simulation')->where('point_of_sale', $pointOfSale)
            ->where('voucher_type', $voucherType)->max('number');
    }

    /** En simulación nunca hay envíos sin respuesta: no hay nada que consultar. */
    public function consult(int $pointOfSale, int $voucherType, int $number): ?array
    {
        return null;
    }

    public function testConnection(): ArcaResult
    {
        return new ArcaResult(true, operation: 'FEDummy', response: ['simulado' => true]);
    }
}
