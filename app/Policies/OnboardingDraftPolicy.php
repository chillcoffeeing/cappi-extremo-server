<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\OnboardingDraft;

class OnboardingDraftPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('onboarding.view');
    }

    public function view(AdminUser $adminUser, OnboardingDraft $draft): bool
    {
        return $adminUser->can('onboarding.view');
    }

    public function update(AdminUser $adminUser, OnboardingDraft $draft): bool
    {
        return $adminUser->can('onboarding.manage');
    }

    /** F-053: el borrado (antes onboarding.manage, F-025) es solo de SUPER_ADMIN. */
    public function delete(AdminUser $adminUser, OnboardingDraft $draft): bool
    {
        return $adminUser->can('records.delete');
    }
}
