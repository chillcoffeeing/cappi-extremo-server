<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birthDate' => ['required', 'date_format:Y-m-d'],
            'gender' => ['required', 'in:MASCULINO,FEMENINO,OTRO,PREFIERO_NO_DECIR'],
        ];
    }
}
