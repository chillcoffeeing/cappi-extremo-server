<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_the_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_user_with_access_permission_can_access_the_panel(): void
    {
        $admin = AdminUser::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('admin.access', 'admin'));

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_user_without_access_permission_is_denied(): void
    {
        $admin = AdminUser::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_portal_representative_cannot_authenticate_on_the_admin_guard(): void
    {
        $representative = User::factory()->create(['password' => 'password123']);

        $this->assertFalse(
            Auth::guard('admin')->attempt([
                'email' => $representative->email,
                'password' => 'password123',
            ]),
        );
    }
}
