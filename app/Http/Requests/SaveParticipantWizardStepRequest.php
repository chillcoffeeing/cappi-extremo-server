<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveParticipantWizardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stepId' => ['required', 'string', 'in:datos-basicos,salud,contactos-emergencia,encargado-retiro,seguro-medico'],
            'data' => ['present', 'array'],
        ];
    }
}
