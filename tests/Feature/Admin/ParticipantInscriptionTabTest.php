<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Tests\TestCase;

/**
 * F-015: el tab 'Inscripción' de la ficha del participante muestra los datos
 * de Participant::enrollment() con los mismos labels/valores que
 * EnrollmentForm/EnrollmentsTable, y no falla cuando no hay inscripción.
 */
class ParticipantInscriptionTabTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_inscription_tab_shows_enrollment_fields_formatted(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create(['name' => 'Carlos Pérez']);

        Enrollment::create([
            'participant_uuid' => $participant->uuid,
            'plan_name' => 'Plan Xtremo',
            'session_name' => 'Vespertina',
            'status' => 'PAGADA',
            'plan_type' => 'HERMANOS',
            'total_amount' => 350,
            'sibling_discount' => 25,
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-05',
        ]);

        $this->actingAs($admin, 'admin')
            ->get("/admin/participants/{$participant->getKey()}/details")
            ->assertOk()
            ->assertSee('Inscripción')
            ->assertSee('Plan Xtremo')
            ->assertSee('Vespertina')
            ->assertSee('Hermanos')
            ->assertSee('Pagada')
            ->assertSee('Modalidad')
            ->assertSee('Descuento hermanos')
            // Mismo formato de montos que EnrollmentsTable (money USD, locale es).
            ->assertSee(Number::currency(350, 'USD', 'es'))
            ->assertSee(Number::currency(25, 'USD', 'es'));
    }

    public function test_inscription_tab_shows_no_enrollment_message_without_failing(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create(['name' => 'Lucía Gómez']);

        // Sin enrollment: el tab lo indica y la página carga 200 sin excepción
        // (la resolución de state con la relación null está guardada por closures).
        $this->actingAs($admin, 'admin')
            ->get("/admin/participants/{$participant->getKey()}/details")
            ->assertOk()
            ->assertSee('Sin inscripción');
    }
}
