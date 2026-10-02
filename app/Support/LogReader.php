<?php

namespace App\Support;

/**
 * Lectura de las últimas líneas de los logs de Laravel (storage/logs) sin cargar
 * el archivo entero en memoria, enmascarando posibles secretos.
 */
class LogReader
{
    public const MAX_LINES = 2000;

    /** @return list<string> nombres de archivo laravel*.log, el más reciente primero */
    public static function files(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_values(array_map('basename', $files));
    }

    public static function resolve(?string $name): ?string
    {
        $files = self::files();
        if ($files === []) {
            return null;
        }
        // Sólo se aceptan nombres de la lista (evita recorrer rutas arbitrarias).
        $name = $name !== null && in_array($name, $files, true) ? $name : (in_array('laravel.log', $files, true) ? 'laravel.log' : $files[0]);

        return storage_path('logs/'.$name);
    }

    /** @return list<string> */
    public static function tail(string $path, int $lines = 200): array
    {
        $lines = max(1, min(self::MAX_LINES, $lines));
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunk = 8192;
        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
            $read = min($chunk, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$buffer;
            if (strlen($buffer) > 8 * 1024 * 1024) {
                break; // tope de seguridad: 8 MB
            }
        }
        fclose($handle);

        $result = preg_split('/\r?\n/', rtrim($buffer, "\r\n"));
        $result = array_slice($result, -$lines);

        return array_map([self::class, 'mask'], $result);
    }

    /** Enmascara contraseñas, tokens, claves y credenciales que pudieran aparecer en el log. */
    public static function mask(string $line): string
    {
        $patterns = [
            // Encabezado Authorization: Bearer xxx (antes que la regla genérica, que si no tapa sólo «Bearer»)
            '/(Bearer\s+)[A-Za-z0-9\-._~+\/|]+=*/i' => '$1********',
            // clave=valor / "clave":"valor" / clave: valor
            '/((?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|private[_-]?key|passphrase|authorization|client[_-]?secret)["\']?\s*(?:=>|[:=])\s*["\']?)([^\s"\',&;)]+)/i' => '$1********',
            // Tokens de Sanctum (id|token)
            '/\b\d+\|[A-Za-z0-9]{30,}\b/' => '********',
            // APP_KEY base64
            '/base64:[A-Za-z0-9+\/=]{20,}/' => 'base64:********',
            // Credenciales en URLs (mysql://user:pass@host)
            '/([a-z][a-z0-9+.-]*:\/\/[^:\/\s@]+:)[^@\s\/]+@/i' => '$1********@',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $line);
    }
}
