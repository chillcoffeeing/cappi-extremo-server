<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\ParticipantCorrectionRequest;

class ParticipantCorrectionRequestPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('participants.view');
    }

    public function view(AdminUser $adminUser, ParticipantCorrectionRequest $request): bool
    {
        return $adminUser->can('participants.view');
    }

    public function create(AdminUser $adminUser): bool
    {
        return $adminUser->can('participants.edit');
    }

    public function update(AdminUser $adminUser, ParticipantCorrectionRequest $request): bool
    {
        return $adminUser->can('participants.edit');
    }
}
