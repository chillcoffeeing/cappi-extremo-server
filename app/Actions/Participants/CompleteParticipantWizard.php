<?php

namespace App\Actions\Participants;

use App\Models\Participant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteParticipantWizard
{
    public function handle(Participant $participant): Participant
    {
        $required = WizardStepValidator::STEP_IDS;
        $steps = $participant->wizard_steps ?? [];
        $missing = array_values(array_diff($required, array_keys($steps)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'steps' => ['Faltan pasos: '.implode(', ', $missing)],
            ]);
        }

        DB::transaction(function () use ($participant, $steps): void {
            $basic = $steps['datos-basicos'] ?? [];
            $health = $steps['salud'] ?? [];
            $contacts = $steps['contactos-emergencia']['contactosEmergencia']
                ?? $steps['contactos-emergencia'];
            $pickup = $steps['encargado-retiro']['encargadoRetiro']
                ?? $steps['encargado-retiro'];

            $participant->update([
                'name' => $basic['nombre'] ?? $participant->name,
                'birth_date' => $basic['fechaNacimiento'] ?? $participant->birth_date,
                'gender' => $basic['genero'] ?? $participant->gender,
                'identification' => $basic['cedula'] ?? $participant->identification,
                'shirt_size' => $basic['tallaCamisa'] ?? $participant->shirt_size,
                'weight_kg' => $basic['pesoKg'] ?? $participant->weight_kg,
                'health' => $health,
                'emergency_contacts' => is_array($contacts) ? $contacts : [],
                'pickup_contact' => is_array($pickup) ? $pickup : null,
                'data_completed' => true,
            ]);
        });

        return $participant->refresh();
    }
}
