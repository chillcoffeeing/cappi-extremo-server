<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'fecha' => $this->paid_at?->format('Y-m-d'),
            'monto' => (float) $this->amount,
            'moneda' => $this->currency,
            'metodoId' => $this->method_code,
            'metodoNombre' => $this->method_name,
            'referencia' => $this->reference,
            'concepto' => $this->concept,
            'estado' => $this->status,
            'motivoRechazo' => $this->rejection_reason,
            'comprobanteUrl' => $this->receipt_path ? route('payments.receipt', $this->uuid) : '',
            'comprobanteNombre' => $this->receipt_name,
            'ordenId' => $this->order?->uuid,
        ];
    }
}
