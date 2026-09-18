<?php

namespace Tests\Feature\Admin;

use App\Actions\Onboarding\MarkOnboardingAbandoned;
use App\Actions\Onboarding\ReactivateOnboardingFollowUp;
use App\Actions\Participants\MarkParticipantReviewed;
use App\Actions\Participants\RequestParticipantCorrection;
use App\Actions\Participants\ResolveParticipantCorrection;
use App\Exceptions\OnboardingAdminActionException;
use App\Models\AdminUser;
use App\Models\OnboardingDraft;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_correction_does_not_touch_participant_data(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create(['name' => 'Original']);
        $admin = AdminUser::factory()->create();

        $request = app(RequestParticipantCorrection::class)->handle($participant, 'salud', 'Falta tipo de sangre', $admin);

        $this->assertSame('PENDIENTE', $request->status);
        $this->assertSame($admin->uuid, $request->requested_by);
        $this->assertSame('Original', $participant->fresh()->name);
    }

    public function test_resolving_a_correction_stamps_resolver_and_timestamp(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();
        $admin = AdminUser::factory()->create();
        $request = app(RequestParticipantCorrection::class)->handle($participant, 'salud', 'Falta info', $admin);

        $resolved = app(ResolveParticipantCorrection::class)->handle($request, 'Actualizado por el representante', $admin);

        $this->assertSame('RESUELTA', $resolved->status);
        $this->assertSame($admin->uuid, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
    }

    public function test_mark_participant_reviewed(): void
    {
        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create();
        $admin = AdminUser::factory()->create();

        $reviewed = app(MarkParticipantReviewed::class)->handle($participant, $admin);

        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertSame($admin->uuid, $reviewed->reviewed_by);
    }

    public function test_mark_onboarding_abandoned_and_reactivate(): void
    {
        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        $abandoned = app(MarkOnboardingAbandoned::class)->handle($draft);
        $this->assertSame('ABANDONADO', $abandoned->status);

        $reactivated = app(ReactivateOnboardingFollowUp::class)->handle($abandoned);
        $this->assertSame('INCOMPLETO', $reactivated->status);
    }

    public function test_cannot_reactivate_a_draft_that_is_not_abandoned(): void
    {
        $user = User::factory()->create();
        $draft = OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'INCOMPLETO']);

        $this->expectException(OnboardingAdminActionException::class);

        app(ReactivateOnboardingFollowUp::class)->handle($draft);
    }
}
