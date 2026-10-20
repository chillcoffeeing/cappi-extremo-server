<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\OnboardingDrafts\Pages\ListOnboardingDrafts;
use App\Models\AdminUser;
use App\Models\OnboardingDraft;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-025: accion "Eliminar" en /admin/onboarding-drafts para poder borrar
 * casos de prueba de QA manual sin tocar la base de datos a mano.
 * F-053: el borrado pasa de `onboarding.manage` a `records.delete` (solo
 * SUPER_ADMIN); OPERACIONES conserva `onboarding.manage` pero ya no borra.
 */
class OnboardingDraftDeleteActionUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_delete_a_draft_from_the_table(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN'); // F-053: unico rol con records.delete
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        Livewire::test(ListOnboardingDrafts::class)
            ->assertTableActionVisible('delete', $draft)
            ->callTableAction('delete', $draft)
            ->assertHasNoTableActionErrors();

        $this->assertNull(OnboardingDraft::find($draft->getKey()));
    }

    public function test_admin_without_permission_cannot_see_or_execute_the_delete_action(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('OPERACIONES'); // F-053: tiene onboarding.manage pero no records.delete
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        // No la ve en la tabla...
        Livewire::test(ListOnboardingDrafts::class)
            ->assertTableActionHidden('delete', $draft);

        // ...y tampoco puede ejecutarla si se fuerza (la propia Policy, que
        // es lo que Filament consulta para resolver la visibilidad/ejecucion
        // de la Action 'delete', la deniega).
        $this->assertFalse($admin->can('delete', $draft));

        $this->assertNotNull(OnboardingDraft::find($draft->getKey()));
    }

    public function test_deleting_a_draft_does_not_delete_the_associated_user(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'COMPLETADO']);

        Livewire::test(ListOnboardingDrafts::class)
            ->callTableAction('delete', $draft)
            ->assertHasNoTableActionErrors();

        $this->assertNull(OnboardingDraft::find($draft->getKey()));
        $this->assertNotNull(User::find($user->getKey()));
    }
}
