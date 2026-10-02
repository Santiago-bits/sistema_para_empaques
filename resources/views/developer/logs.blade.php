<x-layouts.app title="Logs">
    <x-page-header title="Panel desarrollador" subtitle="Últimas líneas de los logs técnicos (contraseñas y tokens enmascarados)"/>
    @include('developer._nav')

    <form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4" data-allow-resubmit>
        <div>
            <label class="form-label" for="file">Archivo</label>
            <select id="file" name="file" class="form-input" onchange="this.form.submit()">
                @foreach ($files as $f)
                    <option value="{{ $f }}" @selected($f === $current)>{{ $f }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="lines">Líneas</label>
            <select id="lines" name="lines" class="form-input" onchange="this.form.submit()">
                @foreach ([100, 300, 1000, 2000] as $n)
                    <option value="{{ $n }}" @selected($lines === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <p class="pb-2 text-xs text-stone-500">{{ $current ? $current.' · '.app(\App\Services\BackupService::class)->humanSize($size) : 'No hay archivos de log.' }}</p>
    </form>

    <div class="panel overflow-hidden">
        <pre class="max-h-[70vh] overflow-auto bg-stone-950 p-4 font-mono text-xs leading-relaxed text-stone-200">{{ $content ? implode(PHP_EOL, $content) : '(Vacío)' }}</pre>
    </div>
</x-layouts.app>
