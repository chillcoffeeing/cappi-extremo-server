<?php

namespace App\Http\Resources;

use App\Support\PublicPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $enrollment = $this->enrollment;

        return [
            'id' => $this->uuid,
            'datosCompletos' => $this->data_completed,
            'datosBasicos' => [
                'nombre' => $this->name,
                'fechaNacimiento' => $this->birth_date?->format('Y-m-d'),
                'genero' => $this->gender,
                'cedula' => $this->identification,
                'fotoUrl' => PublicPhoto::url($this->photo_url),
                'tallaCamisa' => $this->shirt_size,
                'pesoKg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            ],
            'salud' => $this->health ?? [],
            'contactosEmergencia' => $this->emergency_contacts ?? [],
            // F-076: transicion para pestanas con el portal anterior (lee
            // `seguroMedico.*` sin guardas). Siempre vacio; quitar en la proxima limpieza.
            'seguroMedico' => (object) [],
            'encargadoRetiro' => $this->pickup_contact,
            'solicitudesCorreccion' => $this->whenLoaded('correctionRequests', fn () => $this->correctionRequests
                ->map(fn ($request): array => [
                    'id' => $request->uuid,
                    'seccion' => $request->section,
                    'mensaje' => $request->message,
                    'estado' => $request->status,
                    'resolucion' => $request->resolution,
                    'fecha' => $request->created_at->format('Y-m-d'),
                ])
                ->values(), []),
            'inscripcionActiva' => $enrollment ? [
                'planId' => $enrollment->plan?->uuid ?? '',
                'planNombre' => $enrollment->plan_name,
                'estado' => $enrollment->status,
            ] : null,
            // El portal todavía normaliza esta sección aunque los documentos
            // no formen parte del producto actual; devolver un arreglo evita
            // que las fichas nuevas fallen al renderizarse.
            'documentos' => [],
        ];
    }
}
