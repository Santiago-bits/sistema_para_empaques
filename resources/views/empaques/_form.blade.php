{{-- Campos compartidos por create y edit. Requiere $empaque y $estados. --}}
<div class="mb-3">
    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
    <input type="text" id="nombre" name="nombre" maxlength="150" required
           class="form-control @error('nombre') is-invalid @enderror"
           value="{{ old('nombre', $empaque->nombre) }}">
    @error('nombre')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea id="descripcion" name="descripcion" rows="3" maxlength="2000"
              class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $empaque->descripcion) }}</textarea>
    @error('descripcion')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
    <select id="estado" name="estado" required class="form-select @error('estado') is-invalid @enderror">
        @foreach ($estados as $estado)
            <option value="{{ $estado->value }}" @selected(old('estado', $empaque->estado?->value) === $estado->value)>
                {{ $estado->label() }}
            </option>
        @endforeach
    </select>
    @error('estado')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
