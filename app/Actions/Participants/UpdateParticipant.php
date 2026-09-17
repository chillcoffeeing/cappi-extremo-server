<?php

namespace App\Actions\Participants;

use App\Models\Participant;

class UpdateParticipant
{
    /** @param array<string, mixed> $data */
    public function handle(Participant $participant, array $data): Participant
    {
        foreach (['health', 'emergency_contacts', 'pickup_contact', 'medical_insurance', 'authorizations'] as $field) {
            if (array_key_exists($field, $data) && is_array($data[$field])) {
                $data[$field] = array_replace_recursive($participant->{$field} ?? [], $data[$field]);
            }
        }

        $participant->fill($data)->save();

        return $participant->refresh();
    }
}
