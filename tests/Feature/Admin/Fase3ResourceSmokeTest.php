<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Participant;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase3ResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_users_list_and_details_pages_render_without_edit(): void
    {
        $admin = $this->superAdmin();
        $representative = User::factory()->create(['name' => 'María Pérez']);

        $this->actingAs($admin, 'admin')->get('/admin/users')->assertOk()->assertSee('María Pérez');
        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$representative->getKey()}/details")
            ->assertOk()
            ->assertSee('Ver Representante')
            ->assertSee('Nunca')
            ->assertDontSee('Guardar cambios');

        // /edit ya no existe para representantes.
        $this->actingAs($admin, 'admin')->get("/admin/users/{$representative->getKey()}/edit")->assertNotFound();
    }

    public function test_users_resource_has_no_create_route(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admin')->get('/admin/users/create')->assertNotFound();
    }

    public function test_onboarding_drafts_list_and_details_render_with_actions_without_edit(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        $this->actingAs($admin, 'admin')->get('/admin/onboarding-drafts')->assertOk();
        $this->actingAs($admin, 'admin')
            ->get("/admin/onboarding-drafts/{$draft->getKey()}/details")
            ->assertOk()
            ->assertSee('Marcar abandonado')
            ->assertDontSee('Reactivar seguimiento')
            ->assertDontSee('Guardar cambios');

        // /edit ya no existe para onboarding drafts.
        $this->actingAs($admin, 'admin')->get("/admin/onboarding-drafts/{$draft->getKey()}/edit")->assertNotFound();
    }

    public function test_participants_list_and_details_render_with_actions_without_export(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create(['name' => 'Juan Pérez']);

        $this->actingAs($admin, 'admin')->get('/admin/participants')->assertOk()->assertSee('Juan Pérez');
        $this->actingAs($admin, 'admin')
            ->get("/admin/participants/{$participant->getKey()}/details")
            ->assertOk()
            ->assertSee('Marcar revisión')
            ->assertDontSee('Exportar ficha')
            ->assertDontSee('Guardar cambios');

        // /edit ya no existe para participantes.
        $this->actingAs($admin, 'admin')->get("/admin/participants/{$participant->getKey()}/edit")->assertNotFound();
    }

    public function test_enrollments_list_and_details_render_without_edit(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create(['name' => 'Ana Torres']);
        $enrollment = Enrollment::create([
            'participant_uuid' => $participant->uuid,
            'plan_name' => 'Plan Xtremo', 'session_name' => 'Vespertina',
            'status' => 'PENDIENTE_PAGO', 'plan_type' => 'INDIVIDUAL',
            'total_amount' => 100, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/enrollments')->assertOk();
        $this->actingAs($admin, 'admin')
            ->get("/admin/enrollments/{$enrollment->getKey()}/details")
            ->assertOk()
            ->assertSee('Ver Enrollment')
            ->assertSee('Ana Torres')
            ->assertDontSee('Guardar cambios');

        // /edit ya no existe para inscripciones.
        $this->actingAs($admin, 'admin')->get("/admin/enrollments/{$enrollment->getKey()}/edit")->assertNotFound();
    }

    public function test_plans_navigation_and_new_resources_coexist(): void
    {
        $admin = $this->superAdmin();
        Plan::create([
            'name' => 'Plan de prueba', 'venue' => 'Sede', 'season' => 'Temporada',
            'status' => 'PUBLICADO', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
            'capacity' => 10, 'price' => 100, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
        ]);

        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/plans')->assertOk();
    }
}
