<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tickets de soporte: cada usuario ve los suyos; el desarrollador (super admin) ve y gestiona todos. */
class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support)
    {
    }

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))]]);

        return view('support.index', [
            'tickets' => $this->visibleTo($request->user())->with('user:id,first_name,last_name')->withCount('replies')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->latest('updated_at')->paginate($this->perPage($request))->withQueryString(),
            'isDeveloper' => $request->user()->can('developer'),
            'central' => [
                'enabled' => app(\App\Services\Central\CentralSyncService::class)->enabled(),
                'last_sync' => \Illuminate\Support\Facades\Cache::get(\App\Services\Central\CentralSyncService::LAST_SYNC_KEY),
                'error' => \Illuminate\Support\Facades\Cache::get(\App\Services\Central\CentralSyncService::LAST_ERROR_KEY),
            ],
        ]);
    }

    /** Envía y recibe ya mismo las novedades con el soporte del proveedor (Panel General). */
    public function sync(\App\Services\Central\CentralSyncService $sync): \Illuminate\Http\RedirectResponse
    {
        abort_unless($sync->enabled(), 404);
        try {
            $result = $sync->sync();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo conectar con el soporte central. Revisá la conexión a internet; se reintenta solo cada 5 minutos.');
        }

        return back()->with('success', 'Sincronizado con soporte: '.$result['tickets'].' ticket(s) enviados, '.$result['messages'].' respuesta(s) recibidas.');
    }

    public function create(): View
    {
        return view('support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(SupportService::PRIORITIES))],
        ], [], ['subject' => 'asunto', 'description' => 'descripción', 'priority' => 'prioridad']);

        $ticket = $this->support->create($request->user(), $data);

        return redirect()->route('support.show', $ticket)->with('success', 'Ticket '.$ticket->number.' creado. Te avisamos cuando haya respuesta.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorizeTicket($request->user(), $ticket);

        return view('support.show', [
            'ticket' => $ticket->load('user', 'replies.user'),
            'isDeveloper' => $request->user()->can('developer'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request->user(), $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [], ['body' => 'respuesta']);
        $this->support->reply($ticket, $request->user(), $data['body']);

        return redirect()->route('support.show', $ticket)->with('success', 'Respuesta enviada.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))]]);

        // El usuario sólo puede cerrar su propio ticket; el resto de los estados los maneja el desarrollador.
        if (! $request->user()->can('developer')) {
            $this->authorizeTicket($request->user(), $ticket);
            abort_unless($data['status'] === 'closed', 403);
        }

        $this->support->changeStatus($ticket, $data['status'], $request->user());

        return back()->with('success', 'Estado actualizado: '.SupportTicket::STATUSES[$data['status']].'.');
    }

    private function visibleTo(User $user)
    {
        return SupportTicket::query()->when(! $user->can('developer'), fn ($q) => $q->where('user_id', $user->id));
    }

    /** Ticket ajeno → 404 (no confirma que exista). */
    private function authorizeTicket(User $user, SupportTicket $ticket): void
    {
        abort_unless($user->can('developer') || $ticket->user_id === $user->id, 404);
    }
}
