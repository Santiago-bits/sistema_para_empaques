<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Decide la pantalla inicial según el tipo de usuario. */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->kiosk_mode => redirect()->route('kiosk'),
            $user->can('dashboard.view') => redirect()->route('dashboard'),
            $user->can('production.own') && $user->packer_id !== null => redirect()->route('production.mine'),
            $user->can('portal.view') => redirect()->route('portal.index'),
            $user->can('production.scan') => redirect()->route('production.scan'),
            default => redirect()->route('profile.show'),
        };
    }
}
