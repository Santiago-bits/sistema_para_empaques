<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Services\CashService;
use App\Support\CurrentWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Caja de efectivo: saldo anterior, ingresos, egresos y cierre con arqueo. */
class CashController extends Controller
{
    public function __construct(private readonly CashService $cash)
    {
    }

    public function index(): View
    {
        $session = $this->cash->current();

        return view('treasury.cash.index', [
            'session' => $session,
            'totals' => $session?->totals(),
            'movements' => $session ? $session->movements()->with('user:id,first_name,last_name', 'accountMovement.holder')
                ->latest('moved_at')->latest('id')->get() : collect(),
            'suggestedOpening' => $this->cash->suggestedOpening(),
            'lastClosed' => $this->cash->lastClosed(),
        ]);
    }

    public function sessions(Request $request): View
    {
        return view('treasury.cash.sessions', [
            'sessions' => CashSession::query()->where('warehouse_id', CurrentWarehouse::id())
                ->with('opener:id,first_name,last_name', 'closer:id,first_name,last_name')
                ->latest('opened_at')->paginate($this->perPage($request))->withQueryString(),
        ]);
    }

    public function show(CashSession $session): View
    {
        return view('treasury.cash.show', [
            'session' => $session,
            'totals' => $session->totals(),
            'movements' => $session->movements()->with('user:id,first_name,last_name', 'accountMovement.holder')->orderBy('moved_at')->orderBy('id')->get(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $request->merge(['opening_balance' => parse_number($request->input('opening_balance'))]);
        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['opening_balance' => 'saldo inicial', 'notes' => 'observaciones']);

        $this->cash->open((float) $data['opening_balance'], $data['notes'] ?? null, $request->user());

        return redirect()->route('cash.index')->with('success', 'Caja abierta.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $request->merge(['amount' => parse_number($request->input('amount'))]);
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'category' => ['required', Rule::in(array_keys(CashMovement::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
        ], [], ['direction' => 'tipo', 'category' => 'concepto', 'description' => 'descripción', 'amount' => 'importe']);

        $this->cash->addMovement($data, $request->user());

        return redirect()->route('cash.index')->with('success', ($data['direction'] === 'in' ? 'Ingreso' : 'Egreso').' de '.money($data['amount']).' registrado.');
    }

    public function voidMovement(Request $request, CashMovement $movement): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'motivo']);
        $this->cash->voidMovement($movement, $data['reason'], $request->user());

        return back()->with('success', 'Movimiento de caja anulado.');
    }

    public function close(Request $request): RedirectResponse
    {
        $request->merge(['counted_balance' => parse_number($request->input('counted_balance'))]);
        $data = $request->validate([
            'counted_balance' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['counted_balance' => 'efectivo contado', 'notes' => 'observaciones']);

        $session = $this->cash->current();
        abort_unless($session, 409, 'La caja no está abierta.');
        $closed = $this->cash->close($session, (float) $data['counted_balance'], $data['notes'] ?? null, $request->user());

        $diff = (float) $closed->difference;

        return redirect()->route('cash.show', $closed)->with('success', 'Caja cerrada.'.(abs($diff) > 0.004 ? ' Diferencia: '.money($diff).'.' : ' Sin diferencias.'));
    }
}
