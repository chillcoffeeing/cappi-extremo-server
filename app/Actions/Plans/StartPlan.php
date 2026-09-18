<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * PUBLICADO -> EN_CURSO. Se dispara cuando arranca operativamente la
 * experiencia (día 1), sin necesidad de motivo.
 */
class StartPlan
{
    public function handle(Plan $plan): Plan
    {
        return DB::transaction(function () use ($plan): Plan {
            if ($plan->status !== 'PUBLICADO') {
                throw new PlanTransitionException('Solo un plan publicado puede iniciarse.');
            }

            $plan->update(['status' => 'EN_CURSO']);

            return $plan->fresh();
        });
    }
}
