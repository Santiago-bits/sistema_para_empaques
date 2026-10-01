<?php

namespace App\Http\Controllers\Loads;

use App\Enums\RemitoStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Load;
use App\Models\Remito;
use App\Services\AuditService;
use App\Services\RemitoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RemitoController extends Controller
{
    public function __construct(private readonly RemitoService $remitos)
    {
    }

    public function index(Request $request): View
    {
        $remitos = Remito::query()
            ->with(['loadRecord:id,number', 'client:id,business_name', 'destination:id,name', 'truck:id,plate'])
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.trim((string) $request->query('q')).'%'))
            ->when($request->filled('status') && RemitoStatus::tryFrom((string) $request->query('status')),
                fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('issued_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('issued_at', '<=', $request->date('to')->endOfDay()))
            ->latest('issued_at')->latest('id')
            ->paginate($this->perPage($request))->withQueryString();

        return view('remitos.index', [
            'remitos' => $remitos,
            'statuses' => RemitoStatus::options(),
            'clients' => Client::query()->orderBy('business_name')->pluck('business_name', 'id'),
        ]);
    }

    public function store(Request $request, Load $load): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $remito = $this->remitos->issue($load, $request->user(), $data['notes'] ?? null);

        return redirect()->route('remitos.show', $remito)->with('success', "Remito {$remito->number} emitido.");
    }

    public function show(Remito $remito): View
    {
        $remito->load(['loadRecord', 'client', 'destination', 'truck', 'driver', 'items.variety', 'items.size', 'creator', 'stateHistories.user']);

        return view('remitos.show', [
            'remito' => $remito,
            'qr' => $this->remitos->qrDataUri($remito, 140),
            'publicUrl' => $this->remitos->publicUrl($remito),
        ]);
    }

    public function pdf(Remito $remito, AuditService $audit): Response
    {
        $audit->log('print', $remito, description: 'Descargó el PDF del remito '.$remito->number);

        return $this->remitos->pdf($remito)->download('remito-'.$remito->number.'.pdf');
    }

    public function deliverForm(Remito $remito): View|RedirectResponse
    {
        if ($remito->status !== RemitoStatus::Issued) {
            return redirect()->route('remitos.show', $remito)->with('error', 'El remito no está pendiente de entrega.');
        }

        return view('remitos.deliver', ['remito' => $remito->load('loadRecord', 'client', 'destination')]);
    }

    public function deliver(Request $request, Remito $remito): RedirectResponse
    {
        $data = $request->validate([
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_dni' => ['required', 'string', 'regex:/^[0-9.\s]{7,12}$/'],
            'signature' => ['required', 'string', 'max:800000'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['receiver_name' => 'receptor', 'receiver_dni' => 'DNI del receptor', 'signature' => 'firma']);

        $this->remitos->deliver($remito, $data, $request->user());

        return redirect()->route('remitos.show', $remito)->with('success', 'Entrega registrada.');
    }

    public function void(Request $request, Remito $remito): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->remitos->void($remito, $data['reason'], $request->user());

        return redirect()->route('remitos.show', $remito)->with('success', 'Remito anulado. La carga puede emitir uno nuevo.');
    }

    /** Imagen de la firma (privada, requiere permiso). */
    public function signature(Remito $remito): StreamedResponse
    {
        abort_unless($remito->signature_path && Storage::disk('local')->exists($remito->signature_path), 404);

        return Storage::disk('local')->response($remito->signature_path, 'firma.png', [
            'Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Consulta pública por QR: SÓLO datos limitados (sin precios, CUIT ni datos personales). */
    public function public(string $token): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);
        $remito = Remito::query()->where('public_token', $token)->with(['loadRecord:id,number', 'destination:id,locality,province'])->firstOrFail();

        return view('remitos.public', ['remito' => $remito]);
    }
}
