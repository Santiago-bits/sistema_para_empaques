<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de API. Cada usuario con api.tokens administra los suyos; el super administrador
 * puede crearlos a nombre de un usuario técnico (p.ej. «balanza1») y ver todos.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.tokens.index', [
            'tokens' => PersonalAccessToken::query()->with('tokenable')
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id))
                ->latest('id')->paginate($this->perPage($request))->withQueryString(),
            'owners' => $user->isSuperAdmin()
                ? User::query()->where('status', UserStatus::Active->value)->orderBy('last_name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->full_name.' ('.$u->username.')'])->all()
                : [],
            'abilities' => ApiTokenService::ABILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiTokenService::ABILITIES))],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], ['name' => 'nombre', 'abilities' => 'permisos', 'expires_at' => 'vencimiento']);

        $owner = ! empty($data['owner_id']) ? User::query()->findOrFail($data['owner_id']) : $request->user();
        $token = $this->tokens->create($owner, $data['name'], $data['abilities'], $request->user(),
            ! empty($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at'])->endOfDay() : null);

        return redirect()->route('admin.tokens.index')
            ->with('success', 'Token creado. Copialo ahora: no se vuelve a mostrar.')
            ->with('plain_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $user = $request->user();
        $model = PersonalAccessToken::query()
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id))
            ->findOrFail($token);
        $this->tokens->revoke($model);

        return back()->with('success', 'Token revocado. Los dispositivos que lo usaban dejan de tener acceso.');
    }
}
