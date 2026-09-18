<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\User;

/**
 * Gobierna el acceso ADMINISTRATIVO a representantes del portal. No
 * confundir con la autorizacion del propio representante sobre sus datos,
 * que vive en el guard `web`/Sanctum del API publico.
 */
class UserPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('users.view');
    }

    public function view(AdminUser $adminUser, User $representative): bool
    {
        return $adminUser->can('users.view');
    }
}
