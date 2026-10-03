<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicensePayment;
use App\Services\Central\LicensePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administración general → Clientes: si cada empaque pagó, hasta cuándo, cuánto usa el sistema y si tiene
 * pedidos de soporte abiertos. La ficha técnica (licencia, uso detallado) sigue en el Panel General.
 */
class ClientController extends Controller
{
    public function __construct(private readonly LicensePaymentService $payments)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'payment' => ['nullable', Rule::in(array_keys(License::PAYMENT_STATUSES))],
        ]);

        $clients = License::query()
            ->with('latestReport')
            ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereNotIn('status', ['resolved', 'closed'])])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('client_name', 'like', '%'.addcslashes((string) $term, '%_\\').'%')
                ->orWhere('contact_name', 'like', '%'.addcslashes((string) $term, '%_\\').'%')
                ->orWhere('locality', 'like', '%'.addcslashes((string) $term, '%_\\').'%')))
            ->orderBy('client_name')
            ->get();

        if ($request->query('payment')) {
            $clients = $clients->filter(fn (License $l) => $l->paymentStatus() === $request->query('payment'))->values();
        }

        return view('superadmin.clients.index', ['clients' => $clients]);
    }

    public function show(License $license): View
    {
        return view('superadmin.clients.show', [
            'license' => $license->load('latestReport'),
            'payments' => $license->payments()->with('recorder')->orderByDesc('paid_at')->orderByDesc('id')->get(),
            'methods' => LicensePayment::METHODS,
        ]);
    }

    public function updateFee(Request $request, License $license): RedirectResponse
    {
        $data = $request->validate([
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'fee_currency' => ['required', Rule::in(['ARS', 'USD'])],
        ], [], ['monthly_fee' => 'cuota mensual', 'fee_currency' => 'moneda']);

        $this->payments->setFee($license, $data['monthly_fee'] !== null ? (float) $data['monthly_fee'] : null, $data['fee_currency'], $request->user());

        return redirect()->route('superadmin.clients.show', $license)->with('success', 'Cuota mensual guardada.');
    }

    public function storePayment(Request $request, License $license): RedirectResponse
    {
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'currency' => ['required', Rule::in(['ARS', 'USD'])],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'method' => ['required', Rule::in(array_keys(LicensePayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['amount' => 'importe', 'paid_at' => 'fecha de pago', 'months' => 'meses', 'method' => 'forma de pago', 'reference' => 'comprobante']);

        $payment = $this->payments->register($license, $data, $request->user());

        return redirect()->route('superadmin.clients.show', $license)
            ->with('success', 'Pago registrado. Queda pagado hasta el '.$payment->period_to->format('d/m/Y').'.');
    }

    public function voidPayment(Request $request, License $license, LicensePayment $payment): RedirectResponse
    {
        abort_unless($payment->license_id === $license->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);

        $this->payments->void($payment, $data['reason'], $request->user());

        return redirect()->route('superadmin.clients.show', $license)->with('success', 'Pago anulado.');
    }
}
