<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * BORRADOR|FINALIZADO|CANCELADO -> PUBLICADO. Bloquea (no reemplaza) si ya
 * existe otro plan en un estado operativo, segun la regla de integridad de
 * api/docs/backoffice/02-datos-y-migraciones.md ("Solo un plan puede estar
 * PUBLICADO, EN_CURSO o PAUSADO").
 */
class PublishPlan
{
    public function handle(Plan $plan): Plan
    {
        return DB::transaction(function () use ($plan): Plan {
            $locked = Plan::whereIn('status', Plan::LOCKED_STATUSES)
                ->where('id', '!=', $plan->id)
                ->lockForUpdate()
                ->first();

            if ($locked) {
                throw new PlanTransitionException(
                    "Ya existe un plan operativo ({$locked->name}, estado {$locked->status}). Finalizalo o cancelalo antes de publicar uno nuevo.",
                );
            }

            if (in_array($plan->status, Plan::LOCKED_STATUSES, true)) {
                throw new PlanTransitionException('Este plan ya esta publicado.');
            }

            $plan->update(['status' => 'PUBLICADO', 'paused_from_status' => null]);

            return $plan->fresh();
        });
    }
}
