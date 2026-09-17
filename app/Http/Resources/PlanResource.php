<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => 'plan_'.$this->id,
            'nombre' => $this->name,
            'sede' => $this->venue,
            'temporada' => $this->season,
            'estado' => $this->status,
            'portadaUrl' => $this->cover_url,
            'fechaInicio' => $this->starts_at?->format('Y-m-d'),
            'fechaFin' => $this->ends_at?->format('Y-m-d'),
            'cupoTotal' => $this->capacity,
            'cupoDisponible' => $this->capacity,
            'precio' => (float) $this->price,
            'moneda' => $this->currency,
            'edadMin' => $this->age_min,
            'edadMax' => $this->age_max,
            'descripcion' => $this->description ?? '',
            'actividadesEspacios' => $this->activities ?? [],
            'staff' => $this->staff ?? [],
            'miniMarket' => $this->mini_market ?? [],
            'whatsapp' => $this->whatsapp ?? '',
        ];
    }
}
