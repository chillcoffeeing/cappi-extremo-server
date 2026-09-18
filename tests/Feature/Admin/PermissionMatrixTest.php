<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fase 6 (endurecimiento): fija en codigo la matriz de permisos descrita en
 * api/docs/backoffice/00-vision-y-alcance.md y 01-arquitectura-y-seguridad.md,
 * independiente de PermissionSeeder::ROLE_PERMISSIONS, para detectar si el
 * seeder se desvia de lo documentado sin que nadie lo note.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function roleGrantsProvider(): array
    {
        return [
            'OPERACIONES ve y gestiona plan/participantes/inscripciones/onboarding' => ['OPERACIONES', [
                'admin.access', 'onboarding.view', 'onboarding.manage',
                'plans.view', 'plans.manage', 'plan_content.manage',
                'participants.view', 'participants.edit',
                'enrollments.view', 'enrollments.manage',
            ]],
            'FINANZAS ve y gestiona pagos/ordenes/metodos/tienda/reportes' => ['FINANZAS', [
                'admin.access', 'orders.view', 'orders.manage',
                'payments.view', 'payments.approve', 'payments.reject',
                'payment_methods.manage', 'products.manage', 'reports.export',
            ]],
            'SOPORTE ve/gestiona representantes y onboarding, ve participantes' => ['SOPORTE', [
                'admin.access', 'users.view', 'users.edit',
                'onboarding.view', 'onboarding.manage', 'participants.view',
            ]],
            'LECTURA solo ve, nunca gestiona' => ['LECTURA', [
                'admin.access', 'users.view', 'onboarding.view', 'plans.view',
                'participants.view', 'enrollments.view', 'orders.view',
                'payments.view', 'audit.view',
            ]],
        ];
    }

    private const ALL_PERMISSIONS = [
        'admin.access', 'users.view', 'users.edit', 'onboarding.view', 'onboarding.manage',
        'plans.view', 'plans.manage', 'plan_content.manage', 'participants.view', 'participants.edit',
        'enrollments.view', 'enrollments.manage', 'orders.view', 'orders.manage', 'payments.view',
        'payments.approve', 'payments.reject', 'payment_methods.manage', 'products.manage',
        'reports.export', 'audit.view', 'admin_users.manage',
    ];

    #[DataProvider('roleGrantsProvider')]
    public function test_role_has_exactly_the_documented_permissions(string $role, array $expectedGrants): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole($role);

        foreach (self::ALL_PERMISSIONS as $permission) {
            $shouldHave = in_array($permission, $expectedGrants, true);
            $this->assertSame(
                $shouldHave,
                $admin->can($permission),
                "Rol {$role}: se esperaba can('{$permission}') === ".($shouldHave ? 'true' : 'false'),
            );
        }
    }

    public function test_super_admin_has_every_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        foreach (self::ALL_PERMISSIONS as $permission) {
            $this->assertTrue($admin->can($permission), "SUPER_ADMIN deberia tener '{$permission}'");
        }
    }

    public function test_admin_without_any_role_has_no_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();

        foreach (self::ALL_PERMISSIONS as $permission) {
            $this->assertFalse($admin->can($permission));
        }
    }
}
