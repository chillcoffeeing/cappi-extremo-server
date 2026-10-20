<?php

/*
 * Peticiones de humo a una copia de la app, sin servidor web: cada petición
 * pasa por el kernel HTTP de Laravel en una app recién arrancada.
 *
 *   php smoke.php <carpeta-de-la-app> [dominio-del-admin]
 *
 * Sale con 1 si alguna respuesta es 5xx o no es la esperada. Solo hace GET y
 * un POST sin cuerpo (falla en validación): no escribe datos.
 */

[, $base, $adminHost] = $argv + [null, null, ''];
if (! $base || ! is_file($base.'/artisan')) {
    fwrite(STDERR, "Uso: php smoke.php <carpeta-de-la-app> [dominio-del-admin]\n");
    exit(2);
}

require $base.'/vendor/autoload.php';

$adminUrl = $adminHost ? "https://{$adminHost}/login" : '/admin/login';

// [método, url, códigos esperados, para qué]
$checks = [
    ['GET', '/api/planes/activo', [200, 404], 'plan activo (Inicio del portal)'],
    ['GET', '/api/config/plan-price', [200, 404], 'precio del plan (landing/onboarding)'],
    ['GET', '/api/participantes', [401], 'ruta protegida sin token'],
    ['POST', '/api/auth/login', [422], 'login sin datos (validación)'],
    ['GET', $adminUrl, [200], 'login del panel admin'],
];

$failed = 0;
foreach ($checks as [$method, $url, $expected, $label]) {
    $app = require $base.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create($url, $method, server: ['HTTP_ACCEPT' => 'application/json']);

    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        $status = 'EXC '.get_class($e).': '.$e->getMessage();
    }

    $ok = in_array($status, $expected, true);
    $failed += $ok ? 0 : 1;
    printf("%s %-6s %-45s %s (esperado %s) — %s\n", $ok ? 'ok  ' : 'MAL ', $method, $url, $status, implode('/', $expected), $label);
}

exit($failed ? 1 : 0);
