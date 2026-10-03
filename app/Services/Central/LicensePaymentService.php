<?php

namespace App\Services\Central;

use App\Exceptions\BusinessException;
use App\Models\License;
use App\Models\LicensePayment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cobro del sistema a los empaques clientes. Cada pago cubre N meses a continuación de lo ya pagado
 * («pagado hasta»). Los pagos no se editan ni se borran: se anulan con motivo y se recalcula la cobertura.
 * El estado de pago es informativo: nunca bloquea los datos del cliente.
 */
class LicensePaymentService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /** Cuota mensual del cliente (null o 0 = sin cuota). */
    public function setFee(License $license, ?float $fee, string $currency, User $actor): void
    {
        $old = ['monthly_fee' => $license->monthly_fee, 'fee_currency' => $license->fee_currency];
        $license->update(['monthly_fee' => $fee !== null && $fee > 0 ? round($fee, 2) : null, 'fee_currency' => $currency]);
        $this->audit->log('license_fee', $license, $old, ['monthly_fee' => $license->monthly_fee, 'fee_currency' => $currency],
            'Cuota mensual de '.$license->client_name.': '.($license->monthly_fee ? $currency.' '.$license->monthly_fee : 'sin cuota'));
    }

    /**
     * @param  array{amount: float|string, currency?: string, paid_at: string, months: int, method: string, reference?: ?string, notes?: ?string}  $data
     */
    public function register(License $license, array $data, User $actor): LicensePayment
    {
        return DB::transaction(function () use ($license, $data, $actor) {
            $license = License::query()->lockForUpdate()->findOrFail($license->id);
            $months = max(1, min(36, (int) $data['months']));
            $from = $this->nextPeriodStart($license, Carbon::parse($data['paid_at']));
            $to = $from->copy()->addMonthsNoOverflow($months)->subDay();

            $payment = $license->payments()->create([
                'amount' => round((float) $data['amount'], 2),
                'currency' => $data['currency'] ?? $license->fee_currency ?? 'ARS',
                'paid_at' => $data['paid_at'],
                'months' => $months,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);
            $license->update(['paid_until' => $to->toDateString()]);

            $this->audit->log('license_payment', $license, null, ['payment_id' => $payment->id, 'paid_until' => $to->toDateString()],
                'Pago de '.$license->client_name.': '.$payment->currency.' '.$payment->amount.' ('.$months.' '.($months === 1 ? 'mes' : 'meses').', hasta el '.$to->format('d/m/Y').')');

            return $payment;
        });
    }

    public function void(LicensePayment $payment, string $reason, User $actor): void
    {
        DB::transaction(function () use ($payment, $reason, $actor) {
            $license = License::query()->lockForUpdate()->findOrFail($payment->license_id);
            $updated = LicensePayment::query()->whereKey($payment->id)->whereNull('voided_at')
                ->update(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason, 'updated_at' => now()]);
            if ($updated !== 1) {
                throw new BusinessException('Ese pago ya estaba anulado.');
            }
            // La cobertura vuelve al último pago válido.
            $paidUntil = $license->payments()->valid()->max('period_to');
            $license->update(['paid_until' => $paidUntil]);

            $this->audit->log('license_payment_void', $license, ['payment_id' => $payment->id], ['paid_until' => $paidUntil],
                'Anuló un pago de '.$license->client_name, $reason);
        });
    }

    /** Un pago continúa lo ya pagado; si estaba vencido (o nunca pagó), cubre desde la fecha de pago. */
    private function nextPeriodStart(License $license, Carbon $paidAt): Carbon
    {
        if ($license->paid_until !== null && $license->paid_until->gte($paidAt->copy()->subDay())) {
            return $license->paid_until->copy()->addDay();
        }

        return $paidAt->copy()->startOfDay();
    }
}
