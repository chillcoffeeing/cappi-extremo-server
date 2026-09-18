<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Accion financiera invalida (pago ya procesado, monto excede saldo, etc.).
 * Los llamadores (Filament Actions) la capturan para mostrar un mensaje en
 * vez de un 500.
 */
class PaymentActionException extends RuntimeException {}
