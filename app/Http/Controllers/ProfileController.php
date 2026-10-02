<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password']), 'password_changed_at' => now()])->save();

        // Cierra las demás sesiones abiertas, los tokens de API y el «recordarme»: quien tuviera la
        // contraseña anterior queda afuera. La sesión actual sigue activa.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        $request->session()->regenerate();
        $audit->log('password_change', $user, description: 'Cambio de contraseña propia (se cerraron las demás sesiones)');

        return back()->with('success', 'Contraseña actualizada. Se cerraron tus sesiones en otras computadoras.');
    }

    public function updateTheme(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', 'in:light,dark,system']]);
        $request->user()->forceFill(['theme' => $data['theme']])->saveQuietly();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
