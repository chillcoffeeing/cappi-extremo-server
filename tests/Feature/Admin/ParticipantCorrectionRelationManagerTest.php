<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Participants\Pages\ViewParticipant;
use App\Filament\Resources\Participants\RelationManagers\CorrectionRequestsRelationManager;
use App\Models\AdminUser;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParticipantCorrectionRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_a_correction_from_the_relation_manager(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $this->actingAs($admin, 'admin');

        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();

        Livewire::test(CorrectionRequestsRelationManager::class, [
            'ownerRecord' => $participant,
            'pageClass' => ViewParticipant::class,
        ])
            ->callTableAction('create', data: ['section' => 'salud', 'message' => 'Falta info'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $participant->correctionRequests()->count());
        $this->assertSame($admin->uuid, $participant->correctionRequests()->first()->requested_by);
    }
}
