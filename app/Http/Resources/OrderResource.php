<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'fecha' => $this->ordered_at?->format('Y-m-d'),
            'items' => $this->items ?? [],
            'total' => (float) $this->total,
            'abonado' => (float) $this->paid,
            'estado' => $this->status,
            'esInscripcion' => $this->is_registration,
        ];
    }
}
