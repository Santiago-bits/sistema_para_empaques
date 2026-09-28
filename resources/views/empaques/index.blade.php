@extends('layouts.app')

@section('title', 'Empaques')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0">Empaques</h1>
        <a href="{{ route('empaques.create') }}" class="btn btn-primary">+ Nuevo empaque</a>
    </div>

    <form method="GET" action="{{ route('empaques.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-6">
            <input type="search" name="buscar" value="{{ $busqueda }}" class="form-control"
                   placeholder="Buscar por código o nombre">
        </div>
        <div class="col-8 col-md-3">
            <select name="estado" class="form-select">
                <option value="">Todos los estados</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado->value }}" @selected($estadoSeleccionado === $estado)>
                        {{ $estado->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-4 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
            @if ($busqueda !== '' || $estadoSeleccionado)
                <a href="{{ route('empaques.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">✕</a>
            @endif
        </div>
    </form>

    @if ($empaques->isEmpty())
        <div class="alert alert-info">No se encontraron empaques.</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th class="d-none d-md-table-cell">Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($empaques as $empaque)
                            <tr>
                                <td class="font-monospace text-nowrap">{{ $empaque->codigo }}</td>
                                <td>{{ $empaque->nombre }}</td>
                                <td><x-estado-badge :estado="$empaque->estado" /></td>
                                <td class="d-none d-md-table-cell text-nowrap">{{ $empaque->created_at->format('d/m/Y') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('empaques.show', $empaque) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                    <a href="{{ route('empaques.edit', $empaque) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $empaques->links() }}
        </div>
    @endif
@endsection
