@extends('layouts.app')

@section('title', 'No encontrado')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7 text-center py-5">
            <h1 class="display-6">No encontrado</h1>
            <p class="text-muted">
                La página o el empaque que buscás no existe. Verificá el código e intentá de nuevo.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="{{ route('lector') }}" class="btn btn-primary">Ir al lector QR</a>
                <a href="{{ route('empaques.index') }}" class="btn btn-outline-secondary">Ir al listado</a>
            </div>
        </div>
    </div>
@endsection
