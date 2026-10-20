<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el admin local de Fase 0. Solo para desarrollo: cambiar la
     * contraseña antes de cualquier despliegue fuera de local.
     */
    public function run(): void
    {
        $admin = AdminUser::updateOrCreate(
            ['email' => 'admin@cappixtremo.com'],
            [
                'name' => 'Admin Cappi Xtremo',
                'password' => 'admin12345',
            ],
        );

        // F-056 (api-C-1): antes lo hacía PermissionSeeder (que sí corre en
        // prod). Requiere que PermissionSeeder haya creado el rol antes.
        if (Role::where('name', 'SUPER_ADMIN')->where('guard_name', 'admin')->exists()) {
            $admin->syncRoles(['SUPER_ADMIN']);
        }
    }
}
