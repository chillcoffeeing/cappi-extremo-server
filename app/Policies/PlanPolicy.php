<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Plan;

class PlanPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('plans.view');
    }

    public function view(AdminUser $adminUser, Plan $plan): bool
    {
        return $adminUser->can('plans.view');
    }

    public function create(AdminUser $adminUser): bool
    {
        return $adminUser->can('plans.manage');
    }

    public function update(AdminUser $adminUser, Plan $plan): bool
    {
        return $adminUser->can('plans.manage');
    }

    /**
     * "El borrado de planes con inscripciones debe estar bloqueado; usar
     * estados" (02-datos-y-migraciones.md).
     */
    public function delete(AdminUser $adminUser, Plan $plan): bool
    {
        return $adminUser->can('plans.manage') && ! $plan->enrollments()->exists();
    }
}
