<?php

namespace App\Http\Resources;

use App\Support\PublicPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepresentativeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'perfil' => [
                'id' => $this->uuid,
                'nombre' => $this->name,
                'apellido' => $this->last_name ?? '',
                'email' => $this->email,
                'telefono' => $this->phone ?? '',
                'documento' => $this->identification ?? '',
                'fotoUrl' => PublicPhoto::url($this->photo_url),
                'relacion' => $this->relationship ?? '',
            ],
            'encargadoRetiro' => $this->pickup_contact,
        ];
    }
}
