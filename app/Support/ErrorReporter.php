<?php

namespace App\Support;

use App\Models\SystemError;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * Registra errores técnicos con un código legible (ERR-20260930-0012) que se
 * muestra al usuario en lugar del detalle técnico. El detalle queda en
 * system_errors y en storage/logs para el desarrollador.
 */
class ErrorReporter
{
    private static ?WeakMap $codes = null;

    public static function capture(Throwable $e, string $category = 'exception'): string
    {
        self::$codes ??= new WeakMap;
        if (isset(self::$codes[$e])) {
            return self::$codes[$e];
        }

        $code = 'ERR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        self::$codes[$e] = $code;

        try {
            SystemError::query()->create([
                'code' => $code,
                'category' => $category,
                'message' => Str::limit($e->getMessage(), 2000),
                'exception' => Str::limit($e::class, 250, ''),
                'file' => Str::limit(str_replace(base_path(), '', $e->getFile()), 250, ''),
                'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 20000),
                'url' => app()->runningInConsole() ? 'console' : Str::limit(request()->fullUrl(), 250, ''),
                'user_id' => auth()->id(),
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $inner) {
            // Si la base no está disponible, el error queda igualmente en el log de archivo.
            Log::error('No se pudo registrar el error en system_errors: '.$inner->getMessage());
        }

        return $code;
    }

    public static function codeFor(Throwable $e): ?string
    {
        return self::$codes[$e] ?? null;
    }
}
