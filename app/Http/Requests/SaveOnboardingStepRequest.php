<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveOnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stepId' => ['required', 'string', 'in:cuenta,participantes,pago,adicionales,confirmacion'],
            'data' => ['required', 'array'],
        ];
    }
}
