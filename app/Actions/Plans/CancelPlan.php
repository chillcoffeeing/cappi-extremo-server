<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Cualquier estado no terminal -> CANCELADO. Siempre requiere motivo.
 */
class CancelPlan
{
    public function handle(Plan $plan, string $reason): Plan
    {
        return DB::transaction(function () use ($plan, $reason): Plan {
            if (in_array($plan->status, ['FINALIZADO', 'CANCELADO'], true)) {
                throw new PlanTransitionException('Este plan ya esta en un estado terminal.');
            }

            $plan->update([
                'status' => 'CANCELADO',
                'status_reason' => $reason,
                'paused_from_status' => null,
            ]);

            return $plan->fresh();
        });
    }
}
