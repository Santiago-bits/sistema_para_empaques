<div class="grid gap-4 sm:grid-cols-3">
    <x-input name="width" type="number" min="30" max="200" label="Ancho (mm)" :value="$width"/>
    <x-input name="height" type="number" min="20" max="200" label="Alto (mm)" :value="$height"/>
    <x-input name="copies" type="number" min="1" max="5" label="Copias" value="1"/>
</div>
