<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'categoria' => $this->category,
            'precio' => (float) $this->price,
            'precioAnterior' => $this->previous_price === null ? null : (float) $this->previous_price,
            'variantes' => $this->variants ?? [],
            'imagenes' => $this->images ?? [],
            'descripcion' => $this->description ?? '',
            'sinStock' => ! $this->in_stock,
        ];
    }
}
