<?php

namespace App\Actions\Participants;

use App\Models\Participant;

class SaveParticipantWizardStep
{
    /**
     * F-076: paso eliminado. Una pestana con el portal anterior todavia puede
     * mandarlo durante el despliegue: se acepta y no se guarda nada.
     */
    public const REMOVED_STEP_IDS = ['seguro-medico'];

    /** @param array<string, mixed> $data */
    public function handle(Participant $participant, string $stepId, array $data): Participant
    {
        if (in_array($stepId, self::REMOVED_STEP_IDS, true)) {
            return $participant;
        }

        $data = (new WizardStepValidator)->validate($stepId, $data);

        $steps = $participant->wizard_steps ?? [];
        $steps[$stepId] = $data;
        $participant->update(['wizard_steps' => $steps]);

        return $participant->refresh();
    }
}
