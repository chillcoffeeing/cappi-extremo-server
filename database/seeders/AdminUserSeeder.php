<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el admin local de Fase 0. Solo para desarrollo: cambiar la
     * contraseña antes de cualquier despliegue fuera de local.
     */
    public function run(): void
    {
        AdminUser::updateOrCreate(
            ['email' => 'admin@cappixtremo.com'],
            [
                'name' => 'Admin Cappi Xtremo',
                'password' => 'admin12345',
            ],
        );
    }
}
