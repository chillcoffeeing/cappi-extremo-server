<?php

namespace App\Http\Requests;

/**
 * F-051: `POST /pagos/{payment}/completar-coordinado`. Mismo body y mismas
 * reglas que enlazar-pago (monto con 2 decimales, esCompleto, metodoId,
 * referencia y comprobante obligatorios). `metodoNombre` es opcional: el
 * nombre se toma del metodo activo en BD.
 */
class CompleteCoordinatedPaymentRequest extends LinkOrderPaymentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'metodoNombre' => ['nullable', 'string'],
        ];
    }
}
