<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\OnboardingDraft;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresion: el tab de datos capturados mostraba el JSON crudo de
 * OnboardingDraft::data (Placeholder + JSON_PRETTY_PRINT) en vez de una
 * vista legible por paso del wizard.
 */
class OnboardingDraftDataDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_captured_data_renders_formatted_per_wizard_step_not_as_raw_json(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $user = User::factory()->create();
        $draft = OnboardingDraft::create([
            'user_uuid' => $user->uuid,
            'status' => 'LISTO_PARA_CONFIRMAR',
            'completed_steps' => ['cuenta', 'participantes', 'pago', 'confirmacion'],
            'data' => [
                'cuenta' => [
                    'planId' => 'plan_verano_2026',
                    'representante' => ['nombre' => 'Luis Gómez', 'email' => 'luis@example.com'],
                ],
                'participantes' => [
                    'participantes' => [
                        ['nombre' => 'Sofía Gómez', 'nacimiento' => '2016-04-02'],
                    ],
                ],
                'pago' => ['pago' => ['metodo' => 'Zelle', 'monto' => 150]],
                'confirmacion' => ['confirmacion' => true, 'adicionales' => []],
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get("/admin/onboarding-drafts/{$draft->getKey()}/details")
            ->assertOk();

        // No se compara contra assertDontSee('planId', ...): Livewire
        // siempre serializa el estado crudo del componente en el atributo
        // oculto wire:snapshot, asi que las claves del array aparecen ahi
        // sin importar que tan bien se formatee la UI visible. Lo que
        // importa es que los VALORES formateados y las etiquetas legibles
        // aparezcan como texto visible, no el bloque JSON crudo.
        $response->assertSee('Cuenta y plan')
            ->assertSee('plan_verano_2026')
            ->assertSee('Luis Gómez')
            ->assertSee('Participantes')
            ->assertSee('Sofía Gómez')
            ->assertSee('Zelle')
            ->assertSee('Confirmación')
            ->assertSee('Sí')
            ->assertDontSee('JSON_PRETTY_PRINT')
            ->assertDontSee('{&quot;cuenta&quot;');
    }

    public function test_draft_with_no_captured_data_shows_placeholder_message(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $user = User::factory()->create();
        $draft = OnboardingDraft::create([
            'user_uuid' => $user->uuid,
            'status' => 'INCOMPLETO',
        ]);

        $this->actingAs($admin, 'admin')
            ->get("/admin/onboarding-drafts/{$draft->getKey()}/details")
            ->assertOk()
            ->assertSee('Sin datos capturados todavía');
    }
}
