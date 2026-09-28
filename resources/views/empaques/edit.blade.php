@extends('layouts.app')

@section('title', 'Editar '.$empaque->codigo)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1 class="h3 mb-3">Editar empaque <span class="font-monospace">{{ $empaque->codigo }}</span></h1>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('empaques.update', $empaque) }}">
                        @csrf
                        @method('PUT')
                        @include('empaques._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                            <a href="{{ route('empaques.show', $empaque) }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
