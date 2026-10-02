<x-layouts.app title="Tokens de API">
    <x-page-header title="Tokens de API" subtitle="Para balanzas, sensores e integraciones. El token completo se muestra una sola vez."/>

    @if (session('plain_token'))
        <div class="panel mb-6 border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40" role="alert" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Token nuevo — copialo y guardalo en el dispositivo</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <code class="code max-w-full overflow-x-auto rounded-lg bg-white px-3 py-2 text-sm text-stone-900 select-all dark:bg-stone-900 dark:text-white" x-ref="tk">{{ session('plain_token') }}</code>
                <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard?.writeText($refs.tk.textContent.trim()); copied = true" x-text="copied ? 'Copiado' : 'Copiar'">Copiar</button>
            </div>
            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">Se usa en el encabezado <span class="code">Authorization: Bearer &lt;token&gt;</span>. Si se pierde, revocalo y creá otro.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <x-table>
            <thead><tr><th>Nombre</th>@if ($owners)<th>Usuario</th>@endif<th>Permisos</th><th>Último uso</th><th>Vence</th><th></th></tr></thead>
            <tbody>
                @forelse ($tokens as $token)
                    <tr>
                        <td class="font-medium text-stone-900 dark:text-white">{{ $token->name }}</td>
                        @if ($owners)<td>{{ $token->tokenable?->full_name }}</td>@endif
                        <td>@foreach ($token->abilities as $ability)<x-badge color="stone">{{ $ability }}</x-badge> @endforeach</td>
                        <td class="text-stone-500">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Nunca' }}</td>
                        <td>{{ $token->expires_at ? fdate($token->expires_at) : 'No vence' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}" x-data x-confirm="¿Revocar el token «{{ $token->name }}»? Los dispositivos que lo usan dejan de funcionar.">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">Revocar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-empty colspan="6" message="No hay tokens creados."/>
                @endforelse
            </tbody>
            <x-slot:footer>{{ $tokens->links() }}</x-slot:footer>
        </x-table>

        <x-panel title="Nuevo token">
            <form method="POST" action="{{ route('admin.tokens.store') }}" class="space-y-4">
                @csrf
                <x-input name="name" label="Nombre" required maxlength="60" hint="Ej.: Balanza línea 1, Sensor cámara 2"/>
                @if ($owners)
                    <x-select name="owner_id" label="A nombre de" :options="$owners" :value="auth()->id()" hint="El token puede como máximo lo que puede ese usuario."/>
                @endif
                <fieldset>
                    <legend class="form-label">Permisos</legend>
                    <div class="space-y-2">
                        @foreach ($abilities as $key => $label)
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="abilities[]" value="{{ $key }}" @checked(in_array($key, old('abilities', []), true)) class="mt-0.5 rounded border-stone-300 dark:border-stone-600 dark:bg-stone-900">
                                <span><span class="code">{{ $key }}</span> — {{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('abilities')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>
                <x-input name="expires_at" type="date" label="Vence (opcional)" :min="today()->addDay()->toDateString()"/>
                <button class="btn btn-primary w-full">Crear token</button>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
