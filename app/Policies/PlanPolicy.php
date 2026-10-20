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
     * F-054 (C-5): habilita la accion masiva "Eliminar" del listado. Cada plan
     * se vuelve a autorizar con delete() (authorizeIndividualRecords), asi
     * que un plan con inscripciones nunca se borra.
     */
    public function deleteAny(AdminUser $adminUser): bool
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
