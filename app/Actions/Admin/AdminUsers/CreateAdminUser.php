<?php

namespace App\Actions\Admin\AdminUsers;

use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * F-055 — alta de una cuenta admin con un unico rol. Solo SUPER_ADMIN
 * (`admin_users.manage`, AdminUserPolicy::create).
 */
class CreateAdminUser
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, is_active?: bool}  $data
     */
    public function handle(AdminUser $actor, array $data): AdminUser
    {
        return DB::transaction(function () use ($actor, $data): AdminUser {
            $email = mb_strtolower(trim($data['email']));
            AdminUserRules::assertRole($data['role']);
            AdminUserRules::assertEmailFree($email);

            $admin = AdminUser::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $admin->syncRoles([$data['role']]);

            activity('admin.users')
                ->causedBy($actor)
                ->performedOn($admin)
                ->withProperties(['email' => $admin->email, 'role' => $data['role'], 'is_active' => $admin->is_active])
                ->log('created');

            return $admin;
        });
    }
}
