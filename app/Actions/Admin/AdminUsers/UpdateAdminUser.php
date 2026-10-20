<?php

namespace App\Actions\Admin\AdminUsers;

use App\Exceptions\AdminUserActionException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * F-055 — nombre, email, rol y activo de una cuenta admin. Nadie puede
 * quitarse el acceso a si mismo (rol ni desactivarse) y siempre queda al
 * menos un SUPER_ADMIN activo. Desactivar corta la sesion en curso
 * (EnsureAdminIsActive).
 */
class UpdateAdminUser
{
    /**
     * @param  array{name: string, email: string, role: string, is_active: bool}  $data
     */
    public function handle(AdminUser $actor, AdminUser $target, array $data): AdminUser
    {
        return DB::transaction(function () use ($actor, $target, $data): AdminUser {
            $target = AdminUserRules::lock($target);
            $email = mb_strtolower(trim($data['email']));
            $active = (bool) $data['is_active'];
            $role = $data['role'];

            AdminUserRules::assertRole($role);
            AdminUserRules::assertEmailFree($email, $target);

            $previousRole = $target->getRoleNames()->first();
            $self = $actor->is($target);

            if ($self && ! $active) {
                throw new AdminUserActionException('No puedes desactivar tu propia cuenta.');
            }
            if ($self && $role !== $previousRole) {
                throw new AdminUserActionException('No puedes cambiar tu propio rol.');
            }
            if (AdminUserRules::isActiveSuperAdmin($target) && (! $active || $role !== 'SUPER_ADMIN')) {
                AdminUserRules::assertAnotherActiveSuperAdmin($target, 'Debe quedar al menos un Super admin activo.');
            }

            $changes = array_filter([
                'name' => $target->name !== trim($data['name']) ? trim($data['name']) : null,
                'email' => $target->email !== $email ? $email : null,
                'role' => $previousRole !== $role ? $role : null,
                'is_active' => $target->is_active !== $active ? $active : null,
            ], fn ($value): bool => $value !== null);

            $target->update(['name' => trim($data['name']), 'email' => $email, 'is_active' => $active]);
            if ($previousRole !== $role) {
                $target->syncRoles([$role]);
            }

            if ($changes !== []) {
                activity('admin.users')
                    ->causedBy($actor)
                    ->performedOn($target)
                    ->withProperties(['changes' => $changes, 'previous_role' => $previousRole])
                    ->log('updated');
            }

            return $target->refresh();
        });
    }
}
