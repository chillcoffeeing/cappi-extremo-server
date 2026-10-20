<?php

namespace Database\Seeders;

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
        // F-053: borrado fisico de representantes, participantes, inscripciones,
        // borradores y correcciones. Solo SUPER_ADMIN (no esta en ROLE_PERMISSIONS).
        'records.delete',
        // F-062 (api-A-6): solicitar y resolver solicitudes de correccion de
        // ficha sin poder editar participantes. SOPORTE lo tiene (doc 00 y 06:
        // "Soporte gestiona solicitudes de correccion"). participants.edit
        // tambien habilita (OPERACIONES).
        'corrections.manage',
        // F-062 (api-A-9): descargar comprobantes de pago en el panel. Solo
        // roles financieros (01-arquitectura-y-seguridad: "Los comprobantes
        // solo son accesibles a usuarios con permiso financiero").
        'payments.receipts',
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
            'corrections.manage',
        ],
        'FINANZAS' => [
            'admin.access',
            'orders.view', 'orders.manage',
            'payments.view', 'payments.approve', 'payments.reject',
            'payments.receipts',
            'payment_methods.manage', 'products.manage',
            'reports.export',
        ],
        'SOPORTE' => [
            'admin.access',
            'users.view', 'users.edit',
            'onboarding.view', 'onboarding.manage',
            'participants.view', 'corrections.manage',
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

        // F-056 (api-C-1): este seeder se corre en producción cuando hay
        // permisos nuevos; ya NO promueve a nadie. Solo sincroniza permisos de
        // roles; las asignaciones de rol de los admins existentes no se tocan.
        // El rol del admin de desarrollo lo da AdminUserSeeder (solo local).
    }
}
