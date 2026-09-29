<?php

namespace App\Http\Requests;

use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;

class LinkOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // F-052 (A-7): maximo 2 decimales (evita 20.12345 -> 20.1235 en BD).
            'monto' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'esCompleto' => ['required', 'boolean'],
            'metodoId' => ['required', 'string'],
            'metodoNombre' => ['required', 'string'],
            // F-049: vuelve a ser obligatoria (revierte F-035), igual que el
            // onboarding DIRECTO. TrimStrings + ConvertEmptyStringsToNull
            // convierten "" y "   " en null, asi que `required` los rechaza.
            'referencia' => ['required', 'string', 'max:255'],
            'comprobante' => UploadRules::receipt(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...UploadRules::receiptMessages(),
            'referencia.required' => 'Ingresa el número de referencia del pago.',
            'referencia.max' => 'La referencia no puede superar 255 caracteres.',
            'monto.decimal' => 'El monto admite como máximo 2 decimales.',
        ];
    }
}
