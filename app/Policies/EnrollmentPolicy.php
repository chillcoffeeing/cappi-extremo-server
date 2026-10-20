<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Enrollment;

class EnrollmentPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('enrollments.view');
    }

    public function view(AdminUser $adminUser, Enrollment $enrollment): bool
    {
        return $adminUser->can('enrollments.view');
    }

    /** F-053: solo SUPER_ADMIN. Ver App\Actions\Admin\DeleteEnrollment. */
    public function delete(AdminUser $adminUser, Enrollment $enrollment): bool
    {
        return $adminUser->can('records.delete');
    }
}
