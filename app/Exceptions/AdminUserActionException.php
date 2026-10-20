<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * F-055 — cambio invalido sobre una cuenta admin (quitarse el acceso a uno
 * mismo, dejar el panel sin SUPER_ADMIN activo, rol inexistente...). Las
 * paginas de Filament la capturan para mostrar el mensaje en vez de un 500.
 */
class AdminUserActionException extends RuntimeException {}
