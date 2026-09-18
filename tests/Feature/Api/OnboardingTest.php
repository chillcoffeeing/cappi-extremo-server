<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_draft_is_created_and_completed(): void
    {
        $user = User::factory()->create();
        $draftId = (string) Str::uuid();
        Sanctum::actingAs($user);

        $this->getJson("/api/onboarding/{$draftId}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['draftId', 'version']])
            ->assertJsonPath('data.version', 0);

        foreach (['cuenta', 'participantes', 'pago', 'adicionales', 'confirmacion'] as $step) {
            $this->postJson("/api/onboarding/{$draftId}/step", [
                'stepId' => $step,
                'data' => ['saved' => true],
            ])->assertOk();
        }

        $this->postJson("/api/onboarding/{$draftId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'onboarding_status' => 'COMPLETADO',
        ]);
    }

    public function test_onboarding_participants_are_created_when_completed(): void
    {
        $user = User::factory()->create();
        $draftId = (string) Str::uuid();
        $plan = Plan::create([
            'name' => 'Plan de prueba',
            'venue' => 'Sede de prueba',
            'season' => 'Temporada de prueba',
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
            'capacity' => 20,
            'price' => 150,
            'age_min' => 5,
            'age_max' => 15,
            'status' => 'PUBLICADO',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'cuenta',
            'data' => [
                'planId' => $plan->uuid,
            ],
        ])->assertOk();

        foreach (['pago', 'adicionales', 'confirmacion'] as $step) {
            $this->postJson("/api/onboarding/{$draftId}/step", [
                'stepId' => $step,
                'data' => $step === 'pago'
                    ? ['pago' => ['modalidad' => 'completo', 'metodo' => 'Zelle', 'referencia' => 'REF-001']]
                    : ['saved' => true],
            ])->assertOk();
        }

        $this->postJson("/api/onboarding/{$draftId}/step", [
            'stepId' => 'participantes',
            'data' => [
                'participantes' => [
                    ['nombre' => 'Yessi', 'nacimiento' => '2018-09-10'],
                ],
            ],
        ])->assertOk();

        $this->postJson("/api/onboarding/{$draftId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETADO');

        $this->assertDatabaseHas('participants', [
            'user_uuid' => $user->uuid,
            'name' => 'Yessi',
            'birth_date' => '2018-09-10',
        ]);
        $this->assertDatabaseHas('orders', [
            'user_uuid' => $user->uuid,
            'is_registration' => true,
            'total' => 150,
            'paid' => 0,
            'status' => 'PENDIENTE_PAGO',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'plan_name' => 'Plan de prueba',
            'status' => 'PENDIENTE_PAGO',
        ]);
        $this->assertDatabaseHas('payments', [
            'user_uuid' => $user->uuid,
            'reference' => 'REF-001',
            'status' => 'PENDIENTE_VERIFICACION',
        ]);
    }
}
