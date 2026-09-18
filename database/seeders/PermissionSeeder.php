<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Lista de permisos minimos, tomada literal de
     * api/docs/backoffice/01-arquitectura-y-seguridad.md.
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'admin.access',
        'users.view',
        'users.edit',
        'onboarding.view',
        'onboarding.manage',
        'plans.view',
        'plans.manage',
        'plan_content.manage',
        'participants.view',
        'participants.edit',
        'enrollments.view',
        'enrollments.manage',
        'orders.view',
        'orders.manage',
        'payments.view',
        'payments.approve',
        'payments.reject',
        'payment_methods.manage',
        'products.manage',
        'reports.export',
        'audit.view',
        'admin_users.manage',
    ];

    /**
     * Matriz de roles, segun las responsabilidades de
     * api/docs/backoffice/00-vision-y-alcance.md. SUPER_ADMIN recibe todos
     * los permisos aparte (no se repiten aqui).
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'OPERACIONES' => [
            'admin.access',
            'onboarding.view', 'onboarding.manage',
            'plans.view', 'plans.manage', 'plan_content.manage',
            'participants.view', 'participants.edit',
            'enrollments.view', 'enrollments.manage',
        ],
        'FINANZAS' => [
            'admin.access',
            'orders.view', 'orders.manage',
            'payments.view', 'payments.approve', 'payments.reject',
            'payment_methods.manage', 'products.manage',
            'reports.export',
        ],
        'SOPORTE' => [
            'admin.access',
            'users.view', 'users.edit',
            'onboarding.view', 'onboarding.manage',
            'participants.view',
        ],
        'LECTURA' => [
            'admin.access',
            'users.view', 'onboarding.view', 'plans.view', 'participants.view',
            'enrollments.view', 'orders.view', 'payments.view', 'audit.view',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $superAdmin = Role::findOrCreate('SUPER_ADMIN', 'admin');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'admin')->syncPermissions($permissions);
        }

        $localAdmin = AdminUser::where('email', 'admin@cappixtremo.com')->first();
        $localAdmin?->syncRoles(['SUPER_ADMIN']);
    }
}
