<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * PUBLICADO|EN_CURSO -> PAUSADO. Guarda el estado de origen en
 * `paused_from_status` para que ResumePlan sepa a donde volver.
 */
class PausePlan
{
    public function handle(Plan $plan, string $reason): Plan
    {
        return DB::transaction(function () use ($plan, $reason): Plan {
            if (! in_array($plan->status, ['PUBLICADO', 'EN_CURSO'], true)) {
                throw new PlanTransitionException('Solo un plan publicado o en curso puede pausarse.');
            }

            $plan->update([
                'paused_from_status' => $plan->status,
                'status' => 'PAUSADO',
                'status_reason' => $reason,
            ]);

            return $plan->fresh();
        });
    }
}
