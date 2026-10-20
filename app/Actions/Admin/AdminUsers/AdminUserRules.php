<?php

namespace App\Actions\Admin\AdminUsers;

use App\Exceptions\AdminUserActionException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * F-055 — reglas compartidas de la gestion de cuentas admin. Se llaman
 * dentro de la transaccion de cada Action, con las filas bloqueadas.
 */
class AdminUserRules
{
    /** Roles del panel, de mas a menos alcance (PermissionSeeder). */
    public const ROLES = [
        'SUPER_ADMIN' => 'Super admin (todo, incluido borrar y gestionar admins)',
        'OPERACIONES' => 'Operaciones (plan, participantes, inscripciones)',
        'FINANZAS' => 'Finanzas (órdenes, pagos, métodos de pago, productos)',
        'SOPORTE' => 'Soporte (representantes, onboarding, correcciones)',
        'LECTURA' => 'Lectura (ver todo, sin modificar)',
    ];

    public static function assertRole(string $role): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            throw new AdminUserActionException('Rol no válido.');
        }
    }

    public static function assertEmailFree(string $email, ?AdminUser $except = null): void
    {
        $taken = AdminUser::where('email', $email)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw new AdminUserActionException('Ya existe una cuenta admin con ese email.');
        }
    }

    /**
     * Siempre debe quedar al menos un SUPER_ADMIN activo ademas de $target
     * cuando $target deja de serlo (cambio de rol, desactivacion o borrado).
     */
    public static function assertAnotherActiveSuperAdmin(AdminUser $target, string $message): void
    {
        $others = AdminUser::query()
            ->whereKeyNot($target->getKey())
            ->where('is_active', true)
            ->whereIn('id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'SUPER_ADMIN')
                ->where('model_has_roles.model_type', $target->getMorphClass())
                ->select('model_has_roles.model_id'))
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw new AdminUserActionException($message);
        }
    }

    public static function isActiveSuperAdmin(AdminUser $admin): bool
    {
        return $admin->is_active && $admin->hasRole('SUPER_ADMIN');
    }

    public static function lock(AdminUser $admin): AdminUser
    {
        return AdminUser::whereKey($admin->getKey())->lockForUpdate()->firstOrFail();
    }
}
