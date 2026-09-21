<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'birthDate' => ['sometimes', 'date_format:Y-m-d'],
            'gender' => ['sometimes', 'string', 'in:MASCULINO,FEMENINO,OTRO,PREFIERO_NO_DECIR'],
            'identification' => ['sometimes', 'nullable', 'string', 'max:40'],
            'photoUrl' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'shirtSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'weightKg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'health' => ['sometimes', 'array'],
            'emergencyContacts' => ['sometimes', 'array'],
            'pickupContact' => ['sometimes', 'nullable', 'array'],
            'medicalInsurance' => ['sometimes', 'array'],
        ];
    }
}
