<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['items' => ['required', 'array', 'min:1'], 'items.*.nombre' => ['required', 'string'], 'items.*.variante' => ['required', 'string'], 'items.*.qty' => ['required', 'integer', 'min:1'], 'items.*.precio' => ['required', 'numeric', 'min:0']];
    }
}
