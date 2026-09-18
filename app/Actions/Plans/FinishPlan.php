<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * EN_CURSO -> FINALIZADO. Requiere motivo cuando la finalizacion es manual
 * (antes de que termine por fecha), segun 04-plan-activo-y-contenido.md.
 */
class FinishPlan
{
    public function handle(Plan $plan, ?string $reason = null): Plan
    {
        return DB::transaction(function () use ($plan, $reason): Plan {
            if ($plan->status !== 'EN_CURSO') {
                throw new PlanTransitionException('Solo un plan en curso puede finalizarse.');
            }

            $plan->update([
                'status' => 'FINALIZADO',
                'status_reason' => $reason,
                'paused_from_status' => null,
            ]);

            return $plan->fresh();
        });
    }
}
