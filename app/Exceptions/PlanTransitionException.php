<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Transicion de estado de Plan invalida (origen no permitido o ya existe
 * otro plan ocupando un estado operativo). Los llamadores (Filament Actions,
 * comandos) la capturan para mostrar un mensaje al usuario en vez de un 500.
 */
class PlanTransitionException extends RuntimeException {}
