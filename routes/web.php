<?php

use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

// Sin la vista "welcome" por defecto de Laravel: api.cappixtremo.com no es
// un sitio para navegar, y esa pagina delata el framework a cualquiera que
// visite la raiz. Respuesta vacia en su lugar (el healthcheck real sigue
// siendo /up, registrado aparte en bootstrap/app.php).
Route::get('/', fn () => response()->noContent());

// F-075: manual interno, solo para cuentas admin. Con ADMIN_PANEL_DOMAIN vive
// en ese dominio (administrador.cappixtremo.com/manual) y no en el del API.
$manual = Route::get('/manual/{path?}', ManualController::class)
    ->where('path', '.*')
    ->name('manual');

if ($adminDomain = config('app.admin_panel_domain')) {
    $manual->domain($adminDomain);
}
