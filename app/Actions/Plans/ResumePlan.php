<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * PAUSADO -> el estado del que venia (guardado por PausePlan en
 * `paused_from_status`).
 */
class ResumePlan
{
    public function handle(Plan $plan): Plan
    {
        return DB::transaction(function () use ($plan): Plan {
            if ($plan->status !== 'PAUSADO' || ! $plan->paused_from_status) {
                throw new PlanTransitionException('Este plan no esta pausado.');
            }

            $plan->update([
                'status' => $plan->paused_from_status,
                'paused_from_status' => null,
            ]);

            return $plan->fresh();
        });
    }
}
