@props(['hint' => 'Queda registrado en la auditoría quién cambió qué, cuándo y por qué.'])
{{-- Campo obligatorio de los formularios de corrección. --}}
<div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/30">
    <x-input name="reason" label="Motivo de la corrección" required minlength="5" maxlength="255" placeholder="Ej.: se cargó mal el peso" :hint="$hint"/>
</div>
