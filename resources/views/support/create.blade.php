<x-layouts.app title="Nuevo ticket">
    <x-page-header title="Nuevo ticket de soporte" :back="route('support.index')"/>

    <form method="POST" action="{{ route('support.store') }}" class="space-y-6">
        @csrf
        <x-panel>
            <div class="space-y-4">
                <x-input name="subject" label="Asunto" required maxlength="150" autofocus/>
                <x-select name="priority" label="Prioridad" :options="\App\Services\SupportService::PRIORITIES" value="medium" required/>
                <x-textarea name="description" label="Descripción" rows="6" required maxlength="5000"
                            hint="Contá qué estabas haciendo, qué esperabas y qué pasó. Si apareció un código ERR-…, copialo acá."/>
            </div>
        </x-panel>
        <div class="flex justify-end gap-2">
            <a href="{{ route('support.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Enviar ticket</button>
        </div>
    </form>
</x-layouts.app>
