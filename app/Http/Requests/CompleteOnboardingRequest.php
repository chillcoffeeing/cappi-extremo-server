<?php

namespace App\Http\Requests;

use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-035: `POST /onboarding/{id}/complete` acepta multipart con el
 * comprobante del primer pago. Aqui solo se valida el formato/tamano; si es
 * obligatorio o no (metodo DIRECTO vs COORDINADO_REMOTO) lo decide
 * `CompleteOnboarding`, que es quien conoce el metodo guardado en el draft.
 */
class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['comprobante' => UploadRules::receipt(required: false)];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return UploadRules::receiptMessages();
    }
}
