<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-063 (fe-A-9 / fe-C-2): el Inicio del portal consume `dias` de
 * `GET /planes/activo`. Solo se agregan reglas de lectura: ningun campo se
 * quita ni se renombra.
 */
class PlanPortalHomeTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan Vacacional', 'venue' => 'Finca', 'season' => '2026',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-14', 'ends_at' => '2026-12-18',
            'capacity' => 100, 'price' => 150, 'currency' => 'USD', 'age_min' => 5, 'age_max' => 15,
        ], $extra));
    }

    public function test_hidden_days_are_not_sent_and_days_follow_admin_order(): void
    {
        $plan = $this->plan();
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 2, 'date' => '2026-12-15', 'title' => 'Segundo', 'status' => 'PLANIFICADO', 'sort_order' => 2]);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-14', 'title' => 'Primero', 'status' => 'PLANIFICADO', 'sort_order' => 1]);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 3, 'date' => '2026-12-16', 'title' => 'Oculto', 'status' => 'PLANIFICADO', 'sort_order' => 3, 'is_visible' => false]);

        $this->getJson('/api/planes/activo')
            ->assertOk()
            ->assertJsonCount(2, 'data.dias')
            ->assertJsonPath('data.dias.0.titulo', 'Primero')
            ->assertJsonPath('data.dias.0.fecha', '2026-12-14')
            ->assertJsonPath('data.dias.1.titulo', 'Segundo')
            // `totalDias` sigue contando todos los dias (mismo criterio que el % AUTO).
            ->assertJsonPath('data.avance.totalDias', 3);
    }

    public function test_plan_whose_structured_days_are_all_hidden_does_not_fall_back_to_legacy_json(): void
    {
        $plan = $this->plan(['days' => [['date' => '2026-12-14', 'numeroDia' => 1, 'descripcion' => '<p>Legado</p>']]]);
        PlanDay::create(['plan_uuid' => $plan->uuid, 'day_number' => 1, 'date' => '2026-12-14', 'title' => 'Oculto', 'status' => 'PLANIFICADO', 'is_visible' => false]);

        $this->getJson('/api/planes/activo')
            ->assertOk()
            ->assertJsonCount(0, 'data.dias');
    }

    public function test_legacy_days_json_is_still_returned_when_there_are_no_structured_days(): void
    {
        // Compatibilidad: planes sin `plan_days` siguen devolviendo `plans.days` tal cual.
        $this->plan(['days' => [['date' => '2026-12-14', 'numeroDia' => 1, 'descripcion' => '<p>Legado</p>']]]);

        $this->getJson('/api/planes/activo')
            ->assertOk()
            ->assertJsonPath('data.dias.0.date', '2026-12-14')
            ->assertJsonPath('data.dias.0.descripcion', '<p>Legado</p>')
            ->assertJsonStructure(['data' => ['id', 'nombre', 'sede', 'estado', 'fechaInicio', 'fechaFin', 'fechaTexto', 'dias', 'avance', 'avisos']]);
    }
}
