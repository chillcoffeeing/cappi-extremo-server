<?php

namespace App\Policies;

use App\Models\AdminUser;

class AdminUserPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('admin_users.manage');
    }

    public function view(AdminUser $adminUser, AdminUser $target): bool
    {
        return $adminUser->can('admin_users.manage');
    }

    public function create(AdminUser $adminUser): bool
    {
        return $adminUser->can('admin_users.manage');
    }

    public function update(AdminUser $adminUser, AdminUser $target): bool
    {
        return $adminUser->can('admin_users.manage');
    }

    public function delete(AdminUser $adminUser, AdminUser $target): bool
    {
        return $adminUser->can('admin_users.manage') && $adminUser->isNot($target);
    }
}
