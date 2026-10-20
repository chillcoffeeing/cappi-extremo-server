<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * F-061 (fe-A-4 / api-A-7): `photoUrl` ya no es escribible por PATCH; la
     * foto solo cambia por `PUT /participantes/{id}/foto`. Un portal viejo
     * cacheado que todavia lo manda NO recibe 422: el campo no tiene regla,
     * asi que `validated()` lo descarta y el resto de la ficha se guarda.
     * F-076: igual con `medicalInsurance` (la seccion de seguro ya no existe).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'birthDate' => ['sometimes', 'date_format:Y-m-d'],
            'gender' => ['sometimes', 'string', 'in:MASCULINO,FEMENINO,OTRO,PREFIERO_NO_DECIR'],
            'identification' => ['sometimes', 'nullable', 'string', 'max:40'],
            'shirtSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'weightKg' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'health' => ['sometimes', 'array'],
            'emergencyContacts' => ['sometimes', 'array'],
            'pickupContact' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
