<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * F-061 (fe-A-4 / api-A-7 / fe-B-1): fotos de participantes (`participants/`)
 * y de representantes (`profiles/`) en el disco `public`.
 *
 * - `url()` arma la URL publica siempre con el disco `public`, sin depender de
 *   FILESYSTEM_DISK. Guard idempotente: si la columna tiene una URL absoluta
 *   (dato legado; en prod hay 0 filas al 2026-10-19) se devuelve tal cual en
 *   vez de anidarla.
 * - `deleteIfOwned()` solo borra archivos propios del directorio indicado:
 *   nunca una URL, nunca una ruta con `..`, `\` o absoluta. Asi un valor
 *   manipulado no puede borrar imagenes del catalogo ni de otra familia.
 */
final class PublicPhoto
{
    public static function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function isOwnedPath(?string $path, string $directory): bool
    {
        if (! is_string($path) || $path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || str_contains($path, ':')) {
            return false;
        }

        $prefix = rtrim($directory, '/').'/';

        return str_starts_with($path, $prefix) && strlen($path) > strlen($prefix);
    }

    public static function deleteIfOwned(?string $path, string $directory): bool
    {
        if (! self::isOwnedPath($path, $directory)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }
}
