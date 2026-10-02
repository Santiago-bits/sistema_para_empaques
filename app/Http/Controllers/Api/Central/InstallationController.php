<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\Central\CentralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API del Panel General que consumen los empaques (autenticados por su licencia). */
class InstallationController extends Controller
{
    public function __construct(private readonly CentralService $central)
    {
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate(['metrics' => ['required', 'array'], 'version' => ['nullable', 'string', 'max:20']]);
        $this->central->recordReport($this->license($request), $data['metrics'], $data['version'] ?? null, $request->ip());

        return response()->json(['ok' => true]);
    }

    public function ticket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'status' => ['nullable', 'string', 'max:20'],
            'requester' => ['nullable', 'string', 'max:160'],
            'created_at' => ['nullable', 'date'],
            'replies' => ['nullable', 'array', 'max:200'],
            'replies.*.id' => ['required', 'integer', 'min:1'],
            'replies.*.author' => ['nullable', 'string', 'max:160'],
            'replies.*.body' => ['required', 'string', 'max:10000'],
        ]);
        $ticket = $this->central->upsertTicket($this->license($request), $data);

        return response()->json(['ok' => true, 'ticket' => $ticket->id]);
    }

    public function updates(Request $request): JsonResponse
    {
        return response()->json($this->central->updatesFor($this->license($request)));
    }

    public function acknowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['nullable', 'array', 'max:500'], 'messages.*' => ['integer'],
            'tickets' => ['nullable', 'array', 'max:500'], 'tickets.*' => ['string', 'max:30'],
        ]);
        $this->central->acknowledge($this->license($request), $data['messages'] ?? [], $data['tickets'] ?? []);

        return response()->json(['ok' => true]);
    }

    private function license(Request $request): License
    {
        return $request->attributes->get('license');
    }
}
