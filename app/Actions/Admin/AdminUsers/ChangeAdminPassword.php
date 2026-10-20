<?php

namespace App\Actions\Admin\AdminUsers;

use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * F-055 — nueva contrasena para una cuenta admin. Con AuthenticateSession en
 * el panel, las demas sesiones abiertas de esa cuenta se cierran solas al
 * cambiar el hash. La contrasena nunca se audita.
 */
class ChangeAdminPassword
{
    public function handle(AdminUser $actor, AdminUser $target, string $password): void
    {
        DB::transaction(function () use ($actor, $target, $password): void {
            $target = AdminUserRules::lock($target);
            $target->forceFill(['password' => $password, 'remember_token' => null])->save();

            activity('admin.users')
                ->causedBy($actor)
                ->performedOn($target)
                ->log('password_changed');
        });
    }
}
