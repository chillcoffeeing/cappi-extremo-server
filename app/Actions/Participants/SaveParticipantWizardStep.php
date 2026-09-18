<?php

namespace App\Actions\Participants;

use App\Models\Participant;

class SaveParticipantWizardStep
{
    /** @param array<string, mixed> $data */
    public function handle(Participant $participant, string $stepId, array $data): Participant
    {
        $data = (new WizardStepValidator)->validate($stepId, $data);

        $steps = $participant->wizard_steps ?? [];
        $steps[$stepId] = $data;
        $participant->update(['wizard_steps' => $steps]);

        return $participant->refresh();
    }
}
