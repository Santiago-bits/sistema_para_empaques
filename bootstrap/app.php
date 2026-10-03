<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\KioskMode;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\RestrictToLan;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ErrorReporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/*
| Cachés viejas después de un deploy: el deploy por Git de Hostinger conserva bootstrap/cache (está en
| .gitignore) y Laravel seguiría usando las rutas, la configuración y la lista de paquetes de la versión
| anterior. Si alguna caché es más vieja que el código desplegado (o que el .env: por ejemplo al cambiar de
| base de datos), se borra y Laravel la rearma sola. Cuesta unos pocos «stat» por petición.
*/
(static function (string $base): void {
    $code = 0;
    foreach (['.env', 'composer.json', 'composer.lock', 'config/galpon.php', 'routes/web.php', 'bootstrap/app.php'] as $file) {
        $code = max($code, (int) @filemtime($base.'/'.$file));
    }
    foreach (glob($base.'/bootstrap/cache/*.php') ?: [] as $cache) {
        if ($code > 0 && (int) @filemtime($cache) < $code) {
            @unlink($cache);
        }
    }
})(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleEnabled::class,
            'lan' => RestrictToLan::class,
            'active' => EnsureUserIsActive::class,
            'kiosk' => KioskMode::class,
            'password.fresh' => RequirePasswordChange::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'central' => \App\Http\Middleware\EnsureCentralMode::class,
            'installation' => \App\Http\Middleware\AuthenticateInstallation::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdminConfirmed::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class, RedirectIfNotInstalled::class]);
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class], append: [SecurityHeaders::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
        // Proxies de confianza: SÓLO las IP indicadas en GALPON_TRUSTED_PROXIES (vacío = ninguno).
        // Confiar en toda la LAN permitiría a cualquier PC falsificar su IP con X-Forwarded-For
        // (y así saltear los límites de intentos de login y la restricción por IP).
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('GALPON_TRUSTED_PROXIES', '')))));
        if ($proxies !== []) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errores de negocio: mensaje claro al usuario, sin registrar como falla técnica.
        $exceptions->dontReport([BusinessException::class]);

        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->report(function (Throwable $e) {
            ErrorReporter::capture($e);
        });

        // Instalador con la página vieja (abierta desde antes de actualizar, o la sesión cambió de lugar al crear
        // las tablas): en vez de «Sesión expirada», vuelve al formulario con lo cargado. Si el navegador ni
        // siquiera devuelve la cookie de sesión, explica la causa probable (no tendría sentido reintentar).
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $request->routeIs('install.*', 'login.store')) {
                return null;
            }
            if ($request->routeIs('login.store') && $request->cookies->has((string) config('session.cookie'))) {
                return redirect()->route('login')->withInput($request->only('login'))
                    ->withErrors(['session' => 'La página de ingreso había quedado abierta mucho tiempo. Volvé a escribir tu contraseña y tocá «Ingresar».']);
            }
            if ($request->cookies->has((string) config('session.cookie'))) {
                return redirect()->route('install.show')
                    ->withInput($request->except(['_token', 'admin_password', 'admin_password_confirmation', 'users', 'db_password']))
                    ->withErrors(['install' => 'La página del instalador había quedado vieja (por ejemplo, estaba abierta desde antes de actualizar). '
                        .'Ya se renovó: revisá los datos, volvé a escribir la contraseña del administrador y tocá «Instalar sistema».']);
            }
            $domain = (string) config('session.domain');
            $hint = match (true) {
                (bool) config('session.secure') && ! $request->isSecure() => 'Abrí el sistema con https:// adelante de la dirección.',
                $domain !== '' && $domain !== 'null' && ! str_ends_with($request->getHost(), ltrim($domain, '.')) => 'Abrí el sistema desde su dirección principal (no desde una dirección temporal o alternativa).',
                default => 'El navegador no está guardando las cookies de este sitio: permitilas o probá en otra ventana o navegador.',
            };

            return response()->view('errors.419', ['hint' => $hint, 'retry' => route($request->routeIs('login.store') ? 'login' : 'install.show')], 419);
        });

        // Errores técnicos inesperados: código de referencia en lugar del detalle técnico.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface || $e instanceof ValidationException
                || $e instanceof AuthenticationException || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException || $e instanceof TokenMismatchException
                || config('app.debug')) {
                return null;
            }

            $code = ErrorReporter::codeFor($e) ?? ErrorReporter::capture($e);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'No se pudo completar la operación.', 'code' => $code], 500);
            }

            return response()->view('errors.app', ['code' => $code], 500);
        });
    })->create();
