<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-063 (fe-A-14): el portal retoma el onboarding desde otro navegador con
 * `GET /onboarding/{draftId}` (endpoint existente, sin cambios). Estos tests
 * fijan el shape que el portal lee (`data.<paso>` tal como lo guardo
 * `POST /step`) y que el borrador de una cuenta no se ve desde otra.
 */
class OnboardingResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_steps_are_returned_with_the_shape_the_portal_reads(): void
    {
        $user = User::factory()->create(['onboarding_status' => 'INCOMPLETO']);
        $draftId = $user->uuid;
        Sanctum::actingAs($user);

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'cuenta',
            'data' => ['planId' => 'p1', 'representante' => ['nombre' => 'Ana']],
        ])->assertOk();
        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [['nombre' => 'Luis', 'nacimiento' => '2015-01-02']]],
        ])->assertOk();
        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'pago',
            'data' => ['pago' => ['modalidad' => 'cuotas', 'montoAbonar' => '50', 'metodo' => 'met_zelle', 'metodoNombre' => 'Zelle', 'metodoTipo' => 'DIRECTO', 'referencia' => '123']],
        ])->assertOk();

        $this->getJson("/api/onboarding/{$draftId}")
            ->assertOk()
            ->assertJsonPath('data.draftId', $draftId)
            ->assertJsonPath('data.completedSteps', ['cuenta', 'participantes', 'pago'])
            ->assertJsonPath('data.data.participantes.participantes.0.nombre', 'Luis')
            ->assertJsonPath('data.data.participantes.participantes.0.nacimiento', '2015-01-02')
            ->assertJsonPath('data.data.pago.pago.metodo', 'met_zelle')
            ->assertJsonPath('data.data.pago.pago.modalidad', 'cuotas');
    }

    public function test_another_account_cannot_read_the_draft(): void
    {
        $owner = User::factory()->create(['onboarding_status' => 'INCOMPLETO']);
        Sanctum::actingAs($owner);
        $this->postJson("/api/onboarding/{$owner->uuid}/step", [
            'stepId' => 'participantes',
            'data' => ['participantes' => [['nombre' => 'Luis', 'nacimiento' => '2015-01-02']]],
        ])->assertOk();

        Sanctum::actingAs(User::factory()->create(['onboarding_status' => 'INCOMPLETO']));
        $this->getJson("/api/onboarding/{$owner->uuid}")->assertNotFound();
    }
}
