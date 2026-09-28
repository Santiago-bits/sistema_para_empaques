@extends('layouts.app')

@section('title', $empaque->codigo.' (dado de baja)')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="alert alert-warning">
                <h1 class="h4 alert-heading">Empaque dado de baja</h1>
                <p class="mb-0">
                    El empaque <strong class="font-monospace">{{ $empaque->codigo }}</strong>
                    ({{ $empaque->nombre }}) fue eliminado del sistema el
                    {{ $empaque->deleted_at->format('d/m/Y H:i') }}.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('lector') }}" class="btn btn-primary">Escanear otro</a>
                <a href="{{ route('empaques.index') }}" class="btn btn-outline-secondary">Ir al listado</a>
            </div>
        </div>
    </div>
@endsection
