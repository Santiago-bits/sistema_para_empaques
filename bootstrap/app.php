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
        ]);
        $middleware->web(append: [SecurityHeaders::class, RedirectIfNotInstalled::class]);
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class], append: [SecurityHeaders::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
        // Para usar detrás de un proxy en la LAN (IIS/Apache/Nginx).
        $middleware->trustProxies(at: ['127.0.0.1', '192.168.0.0/16', '10.0.0.0/8', '172.16.0.0/12']);
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
