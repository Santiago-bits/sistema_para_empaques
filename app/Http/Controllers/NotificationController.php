<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centro de notificaciones del usuario (campanita): cada uno ve y marca sólo las suyas. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()
                ->when($request->query('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
                ->paginate($this->perPage($request))->withQueryString(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Marca como leída y abre el enlace asociado (sólo enlaces de este mismo sistema). */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = $item->data['url'] ?? null;

        return $url && $this->isInternal($request, $url) ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function markUnread(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsUnread();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas las notificaciones quedaron marcadas como leídas.');
    }

    private function isInternal(Request $request, string $url): bool
    {
        // «//host» y «/\host» los navegadores los interpretan como otro sitio.
        if (str_starts_with($url, '/') && ! in_array(substr($url, 1, 1), ['/', '\\'], true)) {
            return true;
        }

        return parse_url($url, PHP_URL_HOST) === $request->getHost();
    }
}
