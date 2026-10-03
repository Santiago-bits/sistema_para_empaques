<x-layouts.app title="Balanzas y sensores">
    <x-page-header title="Balanzas y sensores" subtitle="Llaves de conexión para equipos que mandan datos solos al sistema."/>

    <div class="panel mb-6 p-5">
        <p class="font-semibold">¿Para qué sirve esto?</p>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            Si tenés una <strong>balanza</strong> o un <strong>sensor de temperatura</strong> que se conecta al sistema, acá creás su
            «llave de conexión» y se la cargás al equipo (lo hace el técnico que lo instala). Así el equipo manda los pesos o la temperatura
            sin que nadie los escriba a mano.
        </p>
        <p class="mt-2 text-sm font-medium text-stone-800 dark:text-stone-200">Si no tenés equipos así, no hace falta tocar nada en esta pantalla.</p>
    </div>

    @if (session('plain_token'))
        <div class="panel mb-6 border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40" role="alert" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Llave nueva: copiala y pasásela al técnico del equipo</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <code class="code max-w-full overflow-x-auto rounded-lg bg-white px-3 py-2 text-sm text-stone-900 select-all dark:bg-stone-900 dark:text-white" x-ref="tk">{{ session('plain_token') }}</code>
                <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard?.writeText($refs.tk.textContent.trim()); copied = true" x-text="copied ? 'Copiada' : 'Copiar'">Copiar</button>
            </div>
            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">Se muestra sólo esta vez. Si se pierde, anulala y creá otra.</p>
            <details class="mt-2 text-xs text-amber-800 dark:text-amber-300">
                <summary class="cursor-pointer">Dato para el técnico</summary>
                Se envía en el encabezado <span class="code">Authorization: Bearer &lt;llave&gt;</span>.
            </details>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <x-table>
            <thead><tr><th>Equipo</th>@if ($owners)<th>A nombre de</th>@endif<th>Qué puede hacer</th><th>Último uso</th><th>Vence</th><th></th></tr></thead>
            <tbody>
                @forelse ($tokens as $token)
                    <tr>
                        <td class="font-medium text-stone-900 dark:text-white">{{ $token->name }}</td>
                        @if ($owners)<td>{{ $token->tokenable?->full_name }}</td>@endif
                        <td class="text-sm">{{ collect($token->abilities)->map(fn ($a) => $abilities[$a] ?? $a)->join(' · ') }}</td>
                        <td class="text-stone-500">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Nunca' }}</td>
                        <td>{{ $token->expires_at ? fdate($token->expires_at) : 'No vence' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}" x-data x-confirm="¿Anular la llave «{{ $token->name }}»? El equipo que la usa deja de conectarse.">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">Anular</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-empty colspan="6" message="Todavía no hay equipos conectados."/>
                @endforelse
            </tbody>
            <x-slot:footer>{{ $tokens->links() }}</x-slot:footer>
        </x-table>

        <x-panel title="Conectar un equipo nuevo">
            <form method="POST" action="{{ route('admin.tokens.store') }}" class="space-y-4">
                @csrf
                <x-input name="name" label="Nombre del equipo" required maxlength="60" hint="Ej.: Balanza línea 1, Sensor cámara 2"/>
                @if ($owners)
                    <x-select name="owner_id" label="A nombre de" :options="$owners" :value="auth()->id()" hint="El equipo puede hacer, como máximo, lo mismo que ese usuario."/>
                @endif
                <fieldset>
                    <legend class="form-label">¿Qué va a hacer el equipo?</legend>
                    <div class="space-y-2">
                        @foreach ($abilities as $key => $label)
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="abilities[]" value="{{ $key }}" @checked(in_array($key, old('abilities', []), true)) class="mt-0.5 rounded border-stone-300 dark:border-stone-600 dark:bg-stone-900">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('abilities')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>
                <x-input name="expires_at" type="date" label="Vence (opcional)" :min="today()->addDay()->toDateString()" hint="Vacío = no vence."/>
                <button class="btn btn-primary w-full">Crear llave de conexión</button>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
