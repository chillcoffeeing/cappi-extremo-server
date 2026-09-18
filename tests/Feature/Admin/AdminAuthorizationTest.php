<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Plan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_role_has_every_permission(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $this->assertTrue($admin->can('admin_users.manage'));
        $this->assertTrue($admin->can('payments.approve'));
        $this->assertTrue($admin->can('plans.manage'));
    }

    public function test_lectura_role_can_view_but_not_manage(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = AdminUser::factory()->create();
        $admin->assignRole('LECTURA');

        $this->assertTrue($admin->can('plans.view'));
        $this->assertFalse($admin->can('plans.manage'));
        $this->assertFalse($admin->can('payments.approve'));
    }

    public function test_finanzas_role_can_approve_payments_but_not_manage_plans(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = AdminUser::factory()->create();
        $admin->assignRole('FINANZAS');

        $this->assertTrue($admin->can('payments.approve'));
        $this->assertTrue($admin->can('products.manage'));
        $this->assertFalse($admin->can('plans.manage'));
    }

    public function test_plan_policy_matches_the_permission_matrix(): void
    {
        $this->seed(PermissionSeeder::class);

        $plan = Plan::create([
            'name' => 'Plan de prueba',
            'venue' => 'Sede de prueba',
            'season' => 'Temporada de prueba',
            'status' => 'BORRADOR',
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-05',
            'capacity' => 10,
            'price' => 100,
            'currency' => 'USD',
            'age_min' => 4,
            'age_max' => 15,
        ]);

        $operaciones = AdminUser::factory()->create();
        $operaciones->assignRole('OPERACIONES');
        $this->assertTrue($operaciones->can('update', $plan));

        $lectura = AdminUser::factory()->create();
        $lectura->assignRole('LECTURA');
        $this->assertTrue($lectura->can('view', $plan));
        $this->assertFalse($lectura->can('update', $plan));
    }

    public function test_deactivated_admin_is_logged_out_on_the_next_request(): void
    {
        $this->seed(PermissionSeeder::class);

        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

        $admin->update(['is_active' => false]);

        $this->actingAs($admin, 'admin')->get('/admin')->assertForbidden();
    }
}
