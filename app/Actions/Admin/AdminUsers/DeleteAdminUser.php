<?php

namespace App\Actions\Admin\AdminUsers;

use App\Exceptions\AdminUserActionException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * F-055 — borrado fisico de una cuenta admin. Lo que esa cuenta reviso,
 * cancelo o publico se conserva: las FK a admin_users son SET NULL. La
 * auditoria (activity_log) tambien se conserva.
 */
class DeleteAdminUser
{
    public function handle(AdminUser $actor, AdminUser $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $target = AdminUserRules::lock($target);

            if ($actor->is($target)) {
                throw new AdminUserActionException('No puedes borrar tu propia cuenta.');
            }
            if (AdminUserRules::isActiveSuperAdmin($target)) {
                AdminUserRules::assertAnotherActiveSuperAdmin($target, 'No se puede borrar el único Super admin activo.');
            }

            $properties = ['email' => $target->email, 'name' => $target->name, 'role' => $target->getRoleNames()->first()];
            $target->syncRoles([]);
            $target->syncPermissions([]);
            $target->delete();

            activity('admin.users')
                ->causedBy($actor)
                ->withProperties($properties)
                ->log('deleted');
        });
    }
}
