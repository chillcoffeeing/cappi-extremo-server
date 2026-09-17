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
            'id' => $this->id,
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
            'autorizaciones' => $this->authorizations ?? [],
            'encargadoRetiro' => $this->pickup_contact,
            'inscripcionActiva' => $enrollment ? [
                'planId' => $enrollment->plan_id ? 'plan_'.$enrollment->plan_id : '',
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
