<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\ClientTicket;
use App\Models\License;
use App\Models\SupportTicket;
use App\Services\Central\CentralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Panel General: pedidos de soporte de todos los empaques (mejoras, cambios, problemas). */
class TicketController extends Controller
{
    public function __construct(private readonly CentralService $central)
    {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(SupportTicket::STATUSES), ['pending']))],
            'license' => ['nullable', 'integer'],
        ]);
        $status = $request->query('status', 'pending');

        return view('central.tickets.index', [
            'status' => $status,
            'tickets' => ClientTicket::query()->with('license:id,client_name')
                ->when($status === 'pending', fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']))
                ->when($status !== 'pending', fn ($q) => $q->where('status', $status))
                ->when($request->integer('license'), fn ($q, $id) => $q->where('license_id', $id))
                ->latest('last_message_at')->paginate($this->perPage($request))->withQueryString(),
            'clients' => License::query()->orderBy('client_name')->pluck('client_name', 'id'),
        ]);
    }

    public function show(ClientTicket $ticket): View
    {
        return view('central.tickets.show', ['ticket' => $ticket->load('license', 'messages')]);
    }

    public function reply(Request $request, ClientTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
        ], [], ['body' => 'respuesta', 'status' => 'estado']);
        $this->central->reply($ticket, $request->user(), $data['body'], $data['status'] ?? null);

        return redirect()->route('central.tickets.show', $ticket)->with('success', 'Respuesta guardada: el empaque la recibe en su próxima sincronización (cada 5 minutos).');
    }

    public function status(Request $request, ClientTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))]]);
        $this->central->changeStatus($ticket, $data['status']);

        return back()->with('success', 'Estado actualizado.');
    }
}
