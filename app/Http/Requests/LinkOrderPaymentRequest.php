<?php

namespace App\Http\Requests;

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
        return ['monto' => ['required', 'numeric', 'min:0.01'], 'esCompleto' => ['required', 'boolean'], 'metodoId' => ['required', 'string'], 'metodoNombre' => ['required', 'string'], 'referencia' => ['required', 'string', 'max:255'], 'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']];
    }
}
