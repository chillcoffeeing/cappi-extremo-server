<?php

namespace App\Http\Requests;

use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['foto' => UploadRules::photo()];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return UploadRules::photoMessages();
    }
}
