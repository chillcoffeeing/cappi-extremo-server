<?php

use Illuminate\Support\Facades\Route;

// Sin la vista "welcome" por defecto de Laravel: api.cappixtremo.com no es
// un sitio para navegar, y esa pagina delata el framework a cualquiera que
// visite la raiz. Respuesta vacia en su lugar (el healthcheck real sigue
// siendo /up, registrado aparte en bootstrap/app.php).
Route::get('/', fn () => response()->noContent());
