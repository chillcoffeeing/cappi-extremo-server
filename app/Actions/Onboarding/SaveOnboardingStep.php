<?php

namespace App\Actions\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveOnboardingStep
{
    /** @param array<string, mixed> $data */
    public function handle(OnboardingDraft $draft, string $stepId, array $data): OnboardingDraft
    {
        return DB::transaction(function () use ($draft, $stepId, $data): OnboardingDraft {
            // F-059 (api-A-3): el draft se lee bloqueado para que un paso que
            // llega a la vez que el `complete` no lo devuelva a
            // LISTO_PARA_CONFIRMAR despues de completado.
            $draft = OnboardingDraft::whereKey($draft->getKey())->lockForUpdate()->firstOrFail();

            // Mismo criterio que CompleteOnboarding: manda el estado del
            // representante. Si ya completo su onboarding, su draft
            // COMPLETADO no se reabre (respuesta idempotente, 200 con el draft
            // sin cambios: cubre el reintento del portal, que guarda
            // "confirmacion" y "adicionales" antes de `complete`, cuando el
            // `complete` anterior si llego al servidor) y cualquier otro
            // draft responde 422.
            $onboardingStatus = User::where('uuid', $draft->user_uuid)->value('onboarding_status');
            if ($onboardingStatus === 'COMPLETADO') {
                if ($draft->status === 'COMPLETADO') {
                    return $draft;
                }

                throw ValidationException::withMessages([
                    'onboarding' => [CompleteOnboarding::ALREADY_COMPLETED_MESSAGE],
                ]);
            }

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
        });
    }
}
