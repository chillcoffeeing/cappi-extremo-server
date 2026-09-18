<?php

namespace App\Actions\Plans;

use App\Exceptions\PlanTransitionException;
use App\Models\AdminUser;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza el bloque `avance` del plan (ver
 * api/docs/backoffice/04-plan-activo-y-contenido.md). En modo AUTO el
 * porcentaje se calcula a partir de los dias completados; en modo MANUAL el
 * admin fija porcentaje/etiqueta/dia actual y debe dar un motivo. La
 * autorizacion (`plan_content.manage`) la resuelve el llamador (Policy),
 * esta Action no recibe HTTP ni hace authorize().
 *
 * @param  array{progress_mode?: string, progress_percent?: int, progress_label?: string|null, current_day_uuid?: string|null, reason?: string}  $data
 */
class UpdatePlanProgress
{
    public function handle(Plan $plan, array $data, AdminUser $actor): Plan
    {
        $mode = $data['progress_mode'] ?? $plan->progress_mode;

        return DB::transaction(function () use ($plan, $data, $actor, $mode): Plan {
            if ($mode === 'MANUAL') {
                if (empty($data['reason'])) {
                    throw new PlanTransitionException('El cambio manual de avance requiere un motivo.');
                }

                $plan->update([
                    'progress_mode' => 'MANUAL',
                    'progress_percent' => $data['progress_percent'] ?? $plan->progress_percent,
                    'progress_label' => $data['progress_label'] ?? $plan->progress_label,
                    'current_day_uuid' => $data['current_day_uuid'] ?? $plan->current_day_uuid,
                    'progress_note' => $data['reason'],
                    'progress_updated_by' => $actor->uuid,
                    'progress_updated_at' => now(),
                ]);

                return $plan->fresh();
            }

            $totalDays = $plan->planDays()->count();
            $completedDays = $plan->planDays()->where('status', 'COMPLETADO')->count();
            $percent = $totalDays > 0 ? (int) round(($completedDays / $totalDays) * 100) : 0;
            $currentDay = $plan->planDays()->where('status', 'EN_CURSO')->first();

            $plan->update([
                'progress_mode' => 'AUTO',
                'progress_percent' => $percent,
                'progress_label' => null,
                'current_day_uuid' => $currentDay?->uuid,
                'progress_note' => null,
                'progress_updated_by' => $actor->uuid,
                'progress_updated_at' => now(),
            ]);

            return $plan->fresh();
        });
    }
}
