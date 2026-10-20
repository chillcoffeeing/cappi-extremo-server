<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * F-075 — sirve el manual (build estatico de `../manual`, Astro con
 * `base: '/manual'`) solo a cuentas admin activas con acceso al panel. El build
 * vive fuera de git en `config('app.manual_path')` (storage/app/manual); lo
 * sube `manual/scripts/deploy.sh`.
 */
class ManualController extends Controller
{
    /** @var array<string, string> */
    private const MIME = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'txt' => 'text/plain; charset=UTF-8',
        'xml' => 'application/xml',
    ];

    public function __invoke(Request $request, ?string $path = null): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        // Mismo criterio que el panel: cuenta activa y con admin.access.
        abort_unless($admin->canAccessPanel(Filament::getPanel('admin')), 403);

        $root = realpath((string) config('app.manual_path'));
        abort_unless($root !== false && is_file($root.'/index.html'), 404, 'El manual todavía no está publicado en este servidor.');

        $file = $this->resolve($root, (string) $path);
        abort_if($file === null, 404);

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        abort_unless(isset(self::MIME[$extension]), 404);

        return response()->file($file, [
            'Content-Type' => self::MIME[$extension],
            'X-Robots-Tag' => 'noindex, nofollow',
            // Los assets de Astro llevan hash en el nombre; las paginas no.
            'Cache-Control' => str_starts_with($file, $root.DIRECTORY_SEPARATOR.'_astro'.DIRECTORY_SEPARATOR)
                ? 'private, max-age=31536000, immutable'
                : 'private, no-cache',
        ]);
    }

    /** Archivo dentro de $root (carpeta => index.html), o null. Nunca sale de $root. */
    private function resolve(string $root, string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, "\0")) {
            return null;
        }

        $candidate = realpath($root.($path === '' ? '' : '/'.$path));
        if ($candidate !== false && is_dir($candidate)) {
            $candidate = realpath($candidate.'/index.html');
        }

        if ($candidate === false || ! is_file($candidate)) {
            return null;
        }

        return str_starts_with($candidate, $root.DIRECTORY_SEPARATOR) ? $candidate : null;
    }
}
