<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()->load('role')]);
    }

    public function updatePassword(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);
        $audit->log('password_change', $request->user(), description: 'Cambio de contraseña propia');

        return back()->with('success', 'Contraseña actualizada.');
    }

    public function updateTheme(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', 'in:light,dark,system']]);
        $request->user()->forceFill(['theme' => $data['theme']])->saveQuietly();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
