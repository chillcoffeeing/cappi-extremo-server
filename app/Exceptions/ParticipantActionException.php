<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Accion invalida sobre un participante o una de sus solicitudes de
 * correccion (p.ej. resolver una solicitud que ya esta resuelta). Los
 * llamadores (Filament Actions) la capturan para mostrar un mensaje en vez
 * de un 500.
 */
class ParticipantActionException extends RuntimeException {}
