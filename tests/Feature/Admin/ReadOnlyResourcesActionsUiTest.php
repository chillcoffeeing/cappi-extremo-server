<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\OnboardingDrafts\Pages\ViewOnboardingDraft;
use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AdminUser;
use App\Models\OnboardingDraft;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-014: al convertir las paginas de edicion en paginas de solo lectura
 * (ViewRecord), las Actions de estado que viven en el header deben seguir
 * funcionando igual (no son edicion de campos, son transiciones de negocio).
 */
class ReadOnlyResourcesActionsUiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_mark_participant_reviewed_still_works_from_the_view_page(): void
    {
        $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();

        Livewire::test(ViewParticipant::class, ['record' => $participant->getKey()])
            ->callAction('markReviewed')
            ->assertHasNoActionErrors();

        $this->assertNotNull($participant->fresh()->reviewed_at);
    }

    public function test_mark_onboarding_abandoned_and_reactivate_still_work_from_the_view_page(): void
    {
        $this->superAdmin();
        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        Livewire::test(ViewOnboardingDraft::class, ['record' => $draft->getKey()])
            ->callAction('markAbandoned')
            ->assertHasNoActionErrors();

        $this->assertSame('ABANDONADO', $draft->fresh()->status);

        Livewire::test(ViewOnboardingDraft::class, ['record' => $draft->getKey()])
            ->callAction('reactivate')
            ->assertHasNoActionErrors();

        $this->assertSame('INCOMPLETO', $draft->fresh()->status);
    }

    public function test_contact_action_is_visible_on_the_view_page(): void
    {
        $this->superAdmin();
        $user = User::factory()->create(['email' => 'padre@example.com']);
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        Livewire::test(ViewOnboardingDraft::class, ['record' => $draft->getKey()])
            ->assertActionVisible('contact');
    }

    /**
     * F-054: las fichas siguen siendo de solo lectura (sin "Editar"); la
     * unica accion nueva es "Eliminar", y solo para SUPER_ADMIN.
     */
    public function test_view_pages_stay_read_only_and_only_super_admin_gets_delete(): void
    {
        $this->superAdmin();
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->assertActionDoesNotExist('edit')
            ->assertActionVisible('delete');
        Livewire::test(ViewParticipant::class, ['record' => $participant->getKey()])
            ->assertActionDoesNotExist('edit')
            ->assertActionVisible('delete');

        $soporte = AdminUser::factory()->create();
        $soporte->assignRole('SOPORTE'); // users.view + participants.view, sin records.delete
        $this->actingAs($soporte, 'admin');

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertActionHidden('delete');
        Livewire::test(ViewParticipant::class, ['record' => $participant->getKey()])->assertActionHidden('delete');
    }
}
