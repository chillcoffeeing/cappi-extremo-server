<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\PlanAnnouncement;
use App\Models\PlanDay;
use App\Models\PlanDayActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanActiveContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_plan_exposes_structured_days_progress_and_visible_announcements(): void
    {
        $plan = Plan::create([
            'name' => 'Plan Vacacional', 'venue' => 'Finca', 'season' => '2026',
            'status' => 'EN_CURSO', 'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
            'capacity' => 100, 'price' => 150, 'currency' => 'USD', 'age_min' => 5, 'age_max' => 15,
        ]);

        $day1 = PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-07', 'title' => 'Día 1', 'status' => 'COMPLETADO']);
        $day2 = PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 2, 'date' => '2026-12-08', 'title' => 'Día 2', 'status' => 'EN_CURSO']);
        PlanDayActivity::create(['plan_day_uuid' => $day2->uuid, 'title' => 'Natación', 'status' => 'EN_CURSO']);

        $plan->update(['current_day_uuid' => $day2->uuid, 'progress_percent' => 50]);

        PlanAnnouncement::create([
            'plan_uuid' => $plan->uuid, 'title' => 'Visible', 'body' => 'Todo bien', 'severity' => 'INFO', 'is_visible' => true,
        ]);
        PlanAnnouncement::create([
            'plan_uuid' => $plan->uuid, 'title' => 'Oculto', 'body' => 'No mostrar', 'severity' => 'WARNING', 'is_visible' => false,
        ]);

        $response = $this->getJson('/api/planes/activo')->assertOk();

        $response->assertJsonPath('data.avance.porcentaje', 50)
            ->assertJsonPath('data.avance.diaActual', 2)
            ->assertJsonPath('data.avance.totalDias', 2)
            ->assertJsonPath('data.dias.0.numeroDia', $day1->day_number)
            ->assertJsonPath('data.dias.1.actividades.0.titulo', 'Natación')
            ->assertJsonCount(1, 'data.avisos')
            ->assertJsonPath('data.avisos.0.titulo', 'Visible');
    }
}
