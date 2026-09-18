<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $images = collect($this->images ?? [])
            ->map(fn ($image) => filter_var($image, FILTER_VALIDATE_URL) ? $image : Storage::disk('public')->url($image))
            ->values()
            ->all();

        return [
            'id' => $this->uuid,
            'nombre' => $this->name,
            'categoria' => $this->category,
            'precio' => (float) $this->price,
            'precioAnterior' => $this->previous_price === null ? null : (float) $this->previous_price,
            'variantes' => $this->variants ?? [],
            'imagenes' => $images,
            'descripcion' => $this->description ?? '',
            'sinStock' => ! $this->in_stock,
        ];
    }
}
