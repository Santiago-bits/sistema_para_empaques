@extends('layouts.app')

@section('title', $empaque->codigo)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h1 class="h3 mb-0 font-monospace">{{ $empaque->codigo }}</h1>
                <x-estado-badge :estado="$empaque->estado" class="fs-6" />
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8">{{ $empaque->nombre }}</dd>

                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8" style="white-space: pre-line">{{ $empaque->descripcion ?: '—' }}</dd>

                        <dt class="col-sm-4">Creado</dt>
                        <dd class="col-sm-8">{{ $empaque->created_at->format('d/m/Y H:i') }}</dd>

                        <dt class="col-sm-4">Última modificación</dt>
                        <dd class="col-sm-8 mb-0">{{ $empaque->updated_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('empaques.edit', $empaque) }}" class="btn btn-primary">Editar</a>
                <form method="POST" action="{{ route('empaques.destroy', $empaque) }}"
                      onsubmit="return confirm('¿Eliminar el empaque {{ $empaque->codigo }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                </form>
                <a href="{{ route('empaques.index') }}" class="btn btn-outline-secondary ms-sm-auto">Volver al listado</a>
            </div>
        </div>
    </div>
@endsection
