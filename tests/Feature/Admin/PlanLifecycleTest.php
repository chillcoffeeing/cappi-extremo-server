<?php

namespace Tests\Feature\Admin;

use App\Actions\Plans\CancelPlan;
use App\Actions\Plans\FinishPlan;
use App\Actions\Plans\PausePlan;
use App\Actions\Plans\PublishPlan;
use App\Actions\Plans\ResumePlan;
use App\Actions\Plans\StartPlan;
use App\Actions\Plans\UpdatePlanProgress;
use App\Exceptions\PlanTransitionException;
use App\Models\AdminUser;
use App\Models\Plan;
use App\Models\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan de prueba',
            'venue' => 'Sede de prueba',
            'season' => 'Temporada de prueba',
            'status' => 'BORRADOR',
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-05',
            'capacity' => 10,
            'price' => 100,
            'currency' => 'USD',
            'age_min' => 4,
            'age_max' => 15,
        ], $overrides));
    }

    public function test_publish_moves_draft_to_publicado(): void
    {
        $plan = $this->makePlan();

        $published = app(PublishPlan::class)->handle($plan);

        $this->assertSame('PUBLICADO', $published->status);
    }

    public function test_publish_blocks_when_another_plan_is_already_operative(): void
    {
        $this->makePlan(['name' => 'Plan A', 'status' => 'PUBLICADO']);
        $planB = $this->makePlan(['name' => 'Plan B']);

        $this->expectException(PlanTransitionException::class);

        app(PublishPlan::class)->handle($planB);
    }

    public function test_full_lifecycle_publish_start_pause_resume_finish(): void
    {
        $plan = $this->makePlan();

        $plan = app(PublishPlan::class)->handle($plan);
        $this->assertSame('PUBLICADO', $plan->status);

        $plan = app(StartPlan::class)->handle($plan);
        $this->assertSame('EN_CURSO', $plan->status);

        $plan = app(PausePlan::class)->handle($plan, 'Lluvia fuerte');
        $this->assertSame('PAUSADO', $plan->status);
        $this->assertSame('EN_CURSO', $plan->paused_from_status);
        $this->assertSame('Lluvia fuerte', $plan->status_reason);

        $plan = app(ResumePlan::class)->handle($plan);
        $this->assertSame('EN_CURSO', $plan->status);
        $this->assertNull($plan->paused_from_status);

        $plan = app(FinishPlan::class)->handle($plan, 'Cierre exitoso');
        $this->assertSame('FINALIZADO', $plan->status);
    }

    public function test_cancel_requires_reason_and_works_from_any_non_terminal_state(): void
    {
        $plan = $this->makePlan();

        $cancelled = app(CancelPlan::class)->handle($plan, 'Poca demanda');

        $this->assertSame('CANCELADO', $cancelled->status);
        $this->assertSame('Poca demanda', $cancelled->status_reason);
    }

    public function test_cancel_rejects_a_terminal_plan(): void
    {
        $plan = $this->makePlan(['status' => 'FINALIZADO']);

        $this->expectException(PlanTransitionException::class);

        app(CancelPlan::class)->handle($plan, 'motivo');
    }

    public function test_manual_progress_update_requires_reason(): void
    {
        $plan = $this->makePlan();
        $admin = AdminUser::factory()->create();

        $this->expectException(PlanTransitionException::class);

        app(UpdatePlanProgress::class)->handle($plan, ['progress_mode' => 'MANUAL', 'progress_percent' => 50], $admin);
    }

    public function test_auto_progress_update_computes_percent_from_completed_days(): void
    {
        $plan = $this->makePlan();
        $admin = AdminUser::factory()->create();

        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-01', 'title' => 'Día 1', 'status' => 'COMPLETADO']);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 2, 'date' => '2026-12-02', 'title' => 'Día 2', 'status' => 'EN_CURSO']);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 3, 'date' => '2026-12-03', 'title' => 'Día 3', 'status' => 'PLANIFICADO']);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 4, 'date' => '2026-12-04', 'title' => 'Día 4', 'status' => 'PLANIFICADO']);

        $updated = app(UpdatePlanProgress::class)->handle($plan, ['progress_mode' => 'AUTO'], $admin);

        $this->assertSame(25, $updated->progress_percent);
        $this->assertNotNull($updated->current_day_uuid);
    }
}
