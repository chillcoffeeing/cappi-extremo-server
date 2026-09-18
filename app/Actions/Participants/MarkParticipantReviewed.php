<?php

namespace App\Actions\Participants;

use App\Models\AdminUser;
use App\Models\Participant;

class MarkParticipantReviewed
{
    public function handle(Participant $participant, AdminUser $actor): Participant
    {
        $participant->update([
            'reviewed_at' => now(),
            'reviewed_by' => $actor->uuid,
        ]);

        return $participant->fresh();
    }
}
