<?php

namespace App\Actions\Onboarding;

use App\Models\OnboardingDraft;

/**
 * "Marcar abandonado" (06-flujos-y-auditoria.md). Solo lo hace un admin;
 * ningun flujo del portal pone este estado hoy.
 */
class MarkOnboardingAbandoned
{
    public function handle(OnboardingDraft $draft): OnboardingDraft
    {
        $draft->update(['status' => 'ABANDONADO']);

        return $draft->fresh();
    }
}
