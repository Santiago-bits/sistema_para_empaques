<?php

namespace App\Http\Controllers;

use App\Services\InvoicePdfService;
use App\Services\PortalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Portal de cliente / propietario: sólo lectura y sólo sus propios datos (ver PortalService). */
class PortalController extends Controller
{
    public const TABS = ['summary' => 'Resumen', 'stock' => 'Mercadería', 'loads' => 'Cargas', 'remitos' => 'Remitos', 'invoices' => 'Facturas'];

    public function __construct(private readonly PortalService $portal)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user()->load('owner', 'client');
        $tabs = array_filter(self::TABS, fn ($label, $key) => match ($key) {
            'stock' => $user->owner_id !== null,
            'remitos', 'invoices' => $user->client_id !== null,
            default => true,
        }, ARRAY_FILTER_USE_BOTH);
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'summary';
        $perPage = $this->perPage($request);

        return view('portal.index', [
            'user' => $user,
            'linked' => $this->portal->isLinked($user),
            'tabs' => $tabs,
            'tab' => $tab,
            'summary' => $tab === 'summary' ? $this->portal->summary($user) : null,
            'rows' => match ($tab) {
                'stock' => $this->portal->pallets($user)->with('variety:id,name', 'lot:id,code')->withCount('crates')->latest('received_at')->paginate($perPage)->withQueryString(),
                'loads' => $this->portal->loads($user)->with('destination:id,name', 'truck:id,plate')->latest('date')->paginate($perPage)->withQueryString(),
                'remitos' => $this->portal->remitos($user)->with('destination:id,name')->latest('issued_at')->paginate($perPage)->withQueryString(),
                'invoices' => $this->portal->invoices($user)->latest('issued_on')->paginate($perPage)->withQueryString(),
                default => null,
            },
            'recentLoads' => $tab === 'summary' ? $this->portal->loads($user)->with('destination:id,name')->latest('date')->limit(5)->get() : collect(),
        ]);
    }

    public function invoicePdf(Request $request, int $invoice, InvoicePdfService $pdf): Response
    {
        // Se busca DENTRO del scope del usuario: una factura ajena da 404, no 403 (no confirma que exista).
        return $pdf->download($this->portal->invoices($request->user())->findOrFail($invoice));
    }
}
