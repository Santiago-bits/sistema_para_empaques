<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\Legal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Términos y condiciones y política de privacidad (públicas) y su aceptación. */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', ['provider' => Legal::provider(), 'version' => Legal::VERSION]);
    }

    public function privacy(): View
    {
        return view('legal.privacy', ['provider' => Legal::provider(), 'version' => Legal::VERSION]);
    }

    public function accept(Request $request): View|RedirectResponse
    {
        if (! Legal::mustAccept($request->user())) {
            return redirect()->route('home');
        }

        return view('legal.accept', ['provider' => Legal::provider(), 'version' => Legal::VERSION]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Para seguir tenés que marcar que leíste y aceptás los términos.']);
        $user = $request->user();
        $user->forceFill(['terms_version' => Legal::VERSION, 'terms_accepted_at' => now()])->save();
        $audit->log('terms_accepted', $user, null, ['version' => Legal::VERSION, 'ip' => $request->ip()],
            'Aceptó los términos y condiciones y la política de privacidad (versión '.Legal::VERSION.')');

        return redirect()->intended(route('home'))->with('success', 'Gracias. Los términos quedaron aceptados.');
    }
}
