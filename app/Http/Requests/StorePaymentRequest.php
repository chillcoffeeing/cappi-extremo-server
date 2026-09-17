<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'metodoId' => ['required', 'string', 'max:80'],
            'monto' => ['required', 'numeric', 'min:20'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'referencia' => ['required', 'string', 'max:255'],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
