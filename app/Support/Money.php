<?php

namespace App\Support;

/**
 * F-052 (A-4/A-7): formato y normalizacion de montos compartidos por el API.
 *
 * Formato unico acordado con el portal (`portal/src/lib/money.ts`,
 * `formatMonto`): 2 decimales fijos, punto decimal y coma de miles
 * -> `$1,250.50`.
 */
final class Money
{
    /** `1250.5` -> `$1,250.50`. */
    public static function format(float|int|string $amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Monto normalizado a 2 decimales sin separador de miles (`20`, `20.0`,
     * `20.00` -> `20.00`). Se usa en los hashes de idempotencia para que el
     * texto recibido no permita esquivar el dedupe.
     */
    public static function normalize(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
