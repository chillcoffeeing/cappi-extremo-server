<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RepresentativeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'perfil' => [
                'id' => 'fam_'.$this->id,
                'nombre' => $this->name,
                'apellido' => $this->last_name ?? '',
                'email' => $this->email,
                'telefono' => $this->phone ?? '',
                'documento' => $this->identification ?? '',
                'fotoUrl' => $this->photo_url
                    ? rtrim(config('app.url'), '/').Storage::url($this->photo_url)
                    : null,
                'relacion' => $this->relationship ?? '',
            ],
            'encargadoRetiro' => $this->pickup_contact,
        ];
    }
}
