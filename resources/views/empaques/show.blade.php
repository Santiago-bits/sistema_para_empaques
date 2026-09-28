@extends('layouts.app')

@section('title', $empaque->codigo)

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0 font-monospace">{{ $empaque->codigo }}</h1>
        <x-estado-badge :estado="$empaque->estado" class="fs-6" />
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
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

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Código QR</div>
                <div class="card-body text-center">
                    <img src="{{ route('empaques.qr', [$empaque, 'svg']) }}" alt="QR del empaque {{ $empaque->codigo }}"
                         class="img-fluid mb-2" style="max-width: 240px" width="240" height="240">

                    <p class="small text-muted text-break mb-3">{{ $empaque->urlPublica() }}</p>

                    @if ($qrSoloLocal)
                        <div class="alert alert-warning small text-start">
                            El QR apunta a <strong>localhost</strong>: no va a abrir nada si lo escaneás desde otro
                            dispositivo. Configurá <code>APP_URL</code> en el <code>.env</code> antes de imprimir etiquetas reales.
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <a href="{{ route('empaques.etiqueta', $empaque) }}" target="_blank" class="btn btn-dark">
                            Imprimir etiqueta
                        </a>
                        <div class="btn-group">
                            <a href="{{ route('empaques.qr.descargar', [$empaque, 'png']) }}" class="btn btn-outline-secondary">
                                Descargar PNG
                            </a>
                            <a href="{{ route('empaques.qr.descargar', [$empaque, 'svg']) }}" class="btn btn-outline-secondary">
                                Descargar SVG
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
