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
            'monto' => ['required', 'numeric', 'min:0.01'],
            'esCompleto' => ['required', 'boolean'],
            'metodoId' => ['required', 'string'],
            'metodoNombre' => ['required', 'string'],
            // F-035: el portal la muestra como "Opcional" y envia "" cuando
            // queda vacia (ConvertEmptyStringsToNull la vuelve null); antes era
            // `required` y "Realizar pago" sin referencia fallaba siempre.
            'referencia' => ['nullable', 'string', 'max:255'],
            'comprobante' => UploadRules::receipt(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return UploadRules::receiptMessages();
    }
}
