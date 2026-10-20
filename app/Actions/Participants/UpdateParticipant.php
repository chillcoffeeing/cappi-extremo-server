<?php

namespace App\Actions\Participants;

use App\Models\Participant;

/**
 * PATCH /participantes/{id} (y edicion equivalente).
 *
 * F-061 (fe-A-5 / fe-A-17):
 * - `emergency_contacts` es una lista: se REEMPLAZA completa (el merge por
 *   indice duplicaba contactos al borrar uno).
 * - `pickup_contact`: `null` lo quita; un objeto reemplaza al anterior.
 * - `medical_insurance` con `noTiene=true` se reemplaza (no conserva la
 *   aseguradora ni la poliza vieja); sin `noTiene` sigue el merge de objeto.
 * - `health` (objeto) conserva el merge profundo: las claves omitidas se
 *   mantienen.
 */
class UpdateParticipant
{
    /** @param array<string, mixed> $data */
    public function handle(Participant $participant, array $data): Participant
    {
        if (array_key_exists('emergency_contacts', $data)) {
            $data['emergency_contacts'] = is_array($data['emergency_contacts'])
                ? array_values($data['emergency_contacts'])
                : [];
        }

        if (array_key_exists('health', $data) && is_array($data['health'])) {
            $data['health'] = array_replace_recursive($participant->health ?? [], $data['health']);
        }

        if (array_key_exists('medical_insurance', $data) && is_array($data['medical_insurance'])) {
            $insurance = $data['medical_insurance'];
            $data['medical_insurance'] = filter_var($insurance['noTiene'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? $insurance
                : array_replace_recursive($participant->medical_insurance ?? [], $insurance);
        }

        // pickup_contact: null o un objeto completo; se guarda tal cual llega.

        $participant->fill($data)->save();

        return $participant->refresh();
    }
}
