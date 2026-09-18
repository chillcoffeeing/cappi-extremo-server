<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Accion administrativa invalida sobre un OnboardingDraft (p.ej. reactivar
 * uno que no esta abandonado). Los llamadores (Filament Actions) la
 * capturan para mostrar un mensaje en vez de un 500.
 */
class OnboardingAdminActionException extends RuntimeException {}
