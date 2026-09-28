@extends('layouts.app')

@section('title', 'Nuevo empaque')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1 class="h3 mb-3">Nuevo empaque</h1>
            <p class="text-muted">El código único se genera automáticamente al guardar.</p>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('empaques.store') }}">
                        @csrf
                        @include('empaques._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                            <a href="{{ route('empaques.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
