@extends('layouts.app')

@section('title', 'Lector QR')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="h3 mb-3">Lector QR</h1>

            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <div id="aviso-https" class="alert alert-warning text-start d-none">
                        La cámara solo funciona en conexiones seguras (<strong>https://</strong>) o en
                        <strong>localhost</strong>. Podés ingresar el código manualmente más abajo.
                    </div>
                    <div id="error-camara" class="alert alert-danger text-start d-none" role="alert"></div>

                    <div id="lector" class="mx-auto mb-3" style="max-width: 400px"></div>

                    <button type="button" id="btn-iniciar" class="btn btn-primary btn-lg">Activar cámara</button>
                    <button type="button" id="btn-detener" class="btn btn-outline-secondary btn-lg d-none">Detener</button>
                    <p id="estado-lector" class="text-muted small mt-2 mb-0">
                        Apuntá la cámara al código QR del empaque.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="GET" action="{{ route('lector.buscar') }}">
                        <label for="codigo" class="form-label">¿No podés escanear? Ingresá el código</label>
                        <div class="input-group">
                            <input type="text" id="codigo" name="codigo" placeholder="EMP-XXXXXX"
                                   autocapitalize="characters" autocomplete="off" maxlength="500"
                                   class="form-control font-monospace @error('codigo') is-invalid @enderror">
                            <button type="submit" class="btn btn-outline-primary">Buscar</button>
                            @error('codigo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function () {
            const urlBuscar = @json(route('lector.buscar'));
            const btnIniciar = document.getElementById('btn-iniciar');
            const btnDetener = document.getElementById('btn-detener');
            const estado = document.getElementById('estado-lector');
            const errorCamara = document.getElementById('error-camara');

            // Los navegadores bloquean la cámara fuera de HTTPS/localhost.
            if (!window.isSecureContext || !navigator.mediaDevices) {
                document.getElementById('aviso-https').classList.remove('d-none');
                btnIniciar.disabled = true;
                return;
            }

            const lector = new Html5Qrcode('lector');
            let procesando = false;

            function mostrarError(mensaje) {
                errorCamara.textContent = mensaje;
                errorCamara.classList.remove('d-none');
            }

            function alLeer(texto) {
                if (procesando) return;
                procesando = true;
                estado.textContent = 'Código leído. Buscando empaque…';

                // El servidor extrae el código de la URL y decide a dónde ir.
                lector.stop().finally(function () {
                    window.location.href = urlBuscar + '?codigo=' + encodeURIComponent(texto);
                });
            }

            btnIniciar.addEventListener('click', function () {
                errorCamara.classList.add('d-none');
                btnIniciar.disabled = true;

                lector.start(
                    { facingMode: 'environment' }, // cámara trasera en celulares
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    alLeer,
                    function () { /* frame sin QR: se ignora */ }
                ).then(function () {
                    btnIniciar.classList.add('d-none');
                    btnDetener.classList.remove('d-none');
                }).catch(function (error) {
                    btnIniciar.disabled = false;
                    mostrarError(String(error).includes('NotAllowed')
                        ? 'Permiso de cámara denegado. Habilitalo en la configuración del navegador.'
                        : 'No se pudo iniciar la cámara. Verificá que el dispositivo tenga una disponible.');
                });
            });

            btnDetener.addEventListener('click', function () {
                lector.stop().then(function () {
                    btnDetener.classList.add('d-none');
                    btnIniciar.classList.remove('d-none');
                    btnIniciar.disabled = false;
                });
            });
        })();
    </script>
@endpush
