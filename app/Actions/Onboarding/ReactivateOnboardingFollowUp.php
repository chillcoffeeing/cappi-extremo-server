<?php

namespace App\Actions\Onboarding;

use App\Exceptions\OnboardingAdminActionException;
use App\Models\OnboardingDraft;

/**
 * "Reactivar seguimiento" (06-flujos-y-auditoria.md): vuelve un draft
 * ABANDONADO a un estado operativo para que soporte retome el contacto. El
 * portal recalcula LISTO_PARA_CONFIRMAR vs INCOMPLETO en el siguiente paso
 * guardado (SaveOnboardingStep), asi que aqui basta con volver a INCOMPLETO.
 */
class ReactivateOnboardingFollowUp
{
    public function handle(OnboardingDraft $draft): OnboardingDraft
    {
        if ($draft->status !== 'ABANDONADO') {
            throw new OnboardingAdminActionException('Este draft no esta marcado como abandonado.');
        }

        $draft->update(['status' => 'INCOMPLETO']);

        return $draft->fresh();
    }
}
