<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
                'fotoUrl' => $this->photo_url
                    ? rtrim(config('app.url'), '/').Storage::url($this->photo_url)
                    : null,
                'tallaCamisa' => $this->shirt_size,
                'pesoKg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            ],
            'salud' => $this->health ?? [],
            'contactosEmergencia' => $this->emergency_contacts ?? [],
            'seguroMedico' => $this->medical_insurance ?? [],
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
