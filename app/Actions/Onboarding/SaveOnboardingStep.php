<?php

namespace App\Actions\Onboarding;

use App\Models\OnboardingDraft;

class SaveOnboardingStep
{
    /** @param array<string, mixed> $data */
    public function handle(OnboardingDraft $draft, string $stepId, array $data): OnboardingDraft
    {
        $completed = $draft->completed_steps ?? [];
        if (! in_array($stepId, $completed, true)) {
            $completed[] = $stepId;
        }

        $draft->update([
            'version' => $draft->version + 1,
            'completed_steps' => $completed,
            'data' => array_replace($draft->data ?? [], [$stepId => $data]),
            'status' => in_array('confirmacion', $completed, true)
                ? 'LISTO_PARA_CONFIRMAR'
                : 'INCOMPLETO',
        ]);

        return $draft->refresh();
    }
}
