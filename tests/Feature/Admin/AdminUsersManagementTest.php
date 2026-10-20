<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\AdminUsers\DeleteAdminUser;
use App\Actions\Admin\AdminUsers\UpdateAdminUser;
use App\Exceptions\AdminUserActionException;
use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AdminUser;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * F-055: gestion de cuentas admin desde el panel, solo SUPER_ADMIN
 * (`admin_users.manage`). Nadie se quita el acceso a si mismo y siempre
 * queda un SUPER_ADMIN activo.
 */
class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role, array $attributes = []): AdminUser
    {
        $admin = AdminUser::factory()->create($attributes);
        $admin->assignRole($role);

        return $admin;
    }

    private function actingAsSuperAdmin(): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->admin('SUPER_ADMIN', ['email' => 'jefe@cappixtremo.com']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /** @return array<string, array{string}> */
    public static function nonSuperAdminRoles(): array
    {
        return ['OPERACIONES' => ['OPERACIONES'], 'FINANZAS' => ['FINANZAS'], 'SOPORTE' => ['SOPORTE'], 'LECTURA' => ['LECTURA']];
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_only_super_admin_can_open_the_section(string $role): void
    {
        $this->seed(PermissionSeeder::class);
        $this->actingAs($this->admin($role), 'admin');

        $this->get('/admin/admin-users')->assertForbidden();
        $this->get('/admin/admin-users/create')->assertForbidden();
    }

    public function test_super_admin_lists_accounts(): void
    {
        $me = $this->actingAsSuperAdmin();
        $other = $this->admin('FINANZAS');

        $this->get('/admin/admin-users')->assertOk();
        Livewire::test(ListAdminUsers::class)->assertCanSeeTableRecords([$me, $other]);
    }

    public function test_super_admin_creates_an_account_with_a_role(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAdminUser::class)
            ->fillForm([
                'name' => 'Ana Finanzas', 'email' => 'Ana@Cappixtremo.com', 'role' => 'FINANZAS', 'is_active' => true,
                'password' => 'claveSegura123', 'password_confirmation' => 'claveSegura123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ana = AdminUser::where('email', 'ana@cappixtremo.com')->firstOrFail();
        $this->assertTrue($ana->hasRole('FINANZAS'));
        $this->assertTrue(Hash::check('claveSegura123', $ana->password));
        $this->assertSame(1, Activity::where('log_name', 'admin.users')->where('description', 'created')->count());
        $this->assertStringNotContainsString('claveSegura123', (string) Activity::where('log_name', 'admin.users')->first()->properties);
    }

    public function test_create_validates_password_and_duplicate_email(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAdminUser::class)
            ->fillForm(['name' => 'X', 'email' => 'jefe@cappixtremo.com', 'role' => 'LECTURA', 'password' => 'corta', 'password_confirmation' => 'otra'])
            ->call('create')
            ->assertHasFormErrors(['email', 'password']);

        $this->assertSame(1, AdminUser::count());
    }

    public function test_super_admin_changes_role_and_deactivates_another_account(): void
    {
        $this->actingAsSuperAdmin();
        $other = $this->admin('LECTURA');

        Livewire::test(EditAdminUser::class, ['record' => $other->getKey()])
            ->assertFormSet(['role' => 'LECTURA', 'is_active' => true])
            ->fillForm(['role' => 'OPERACIONES', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $other->refresh();
        $this->assertTrue($other->hasRole('OPERACIONES'));
        $this->assertFalse($other->hasRole('LECTURA'));
        $this->assertFalse($other->is_active);
    }

    public function test_a_deactivated_account_loses_its_open_session(): void
    {
        $this->seed(PermissionSeeder::class);
        $other = $this->admin('OPERACIONES', ['is_active' => false]);

        $this->actingAs($other, 'admin')->get('/admin')->assertForbidden();
    }

    public function test_cannot_deactivate_or_change_role_of_own_account(): void
    {
        $me = $this->actingAsSuperAdmin();

        Livewire::test(ListAdminUsers::class)
            ->assertTableActionHidden('toggleActive', $me)
            ->assertTableActionHidden('delete', $me);

        $this->expectException(AdminUserActionException::class);
        app(UpdateAdminUser::class)->handle($me, $me, ['name' => $me->name, 'email' => $me->email, 'role' => 'SUPER_ADMIN', 'is_active' => false]);
    }

    public function test_cannot_change_own_role_even_forcing_the_form(): void
    {
        $me = $this->actingAsSuperAdmin();

        Livewire::test(EditAdminUser::class, ['record' => $me->getKey()])
            ->set('data.role', 'LECTURA')
            ->call('save');

        $this->assertTrue($me->refresh()->hasRole('SUPER_ADMIN'));
    }

    public function test_the_last_active_super_admin_cannot_be_demoted_deactivated_or_deleted(): void
    {
        $this->seed(PermissionSeeder::class);
        $only = $this->admin('SUPER_ADMIN');
        $inactive = $this->admin('SUPER_ADMIN', ['is_active' => false]);
        $actor = $this->admin('LECTURA');

        foreach ([['role' => 'LECTURA', 'is_active' => true], ['role' => 'SUPER_ADMIN', 'is_active' => false]] as $change) {
            try {
                app(UpdateAdminUser::class)->handle($actor, $only, ['name' => $only->name, 'email' => $only->email] + $change);
                $this->fail('Debio impedir dejar el panel sin Super admin activo.');
            } catch (AdminUserActionException $exception) {
                $this->assertSame('Debe quedar al menos un Super admin activo.', $exception->getMessage());
            }
        }

        try {
            app(DeleteAdminUser::class)->handle($actor, $only);
            $this->fail('Debio impedir borrar el unico Super admin activo.');
        } catch (AdminUserActionException $exception) {
            $this->assertSame('No se puede borrar el único Super admin activo.', $exception->getMessage());
        }

        $this->assertTrue($only->refresh()->is_active && $only->hasRole('SUPER_ADMIN'));
        $this->assertNotNull($inactive->fresh());
    }

    public function test_with_two_active_super_admins_one_can_demote_the_other(): void
    {
        $this->actingAsSuperAdmin();
        $second = $this->admin('SUPER_ADMIN');

        app(UpdateAdminUser::class)->handle(auth('admin')->user(), $second, ['name' => $second->name, 'email' => $second->email, 'role' => 'SOPORTE', 'is_active' => true]);

        $this->assertTrue($second->refresh()->hasRole('SOPORTE'));
    }

    public function test_delete_needs_the_email_and_keeps_what_the_account_did(): void
    {
        $this->actingAsSuperAdmin();
        $other = $this->admin('FINANZAS', ['email' => 'borrar@cappixtremo.com']);

        Livewire::test(ListAdminUsers::class)
            ->callTableAction('delete', $other, data: ['confirmation' => 'otro@cappixtremo.com'])
            ->assertHasTableActionErrors(['confirmation']);
        $this->assertNotNull($other->fresh());

        Livewire::test(ListAdminUsers::class)
            ->callTableAction('delete', $other, data: ['confirmation' => ' BORRAR@cappixtremo.com'])
            ->assertHasNoTableActionErrors();

        $this->assertNull($other->fresh());
        $this->assertSame(0, \DB::table('model_has_roles')->where('model_id', $other->id)->count());
        $this->assertSame(1, Activity::where('log_name', 'admin.users')->where('description', 'deleted')->count());
    }

    public function test_toggle_active_from_the_table(): void
    {
        $this->actingAsSuperAdmin();
        $other = $this->admin('SOPORTE');

        Livewire::test(ListAdminUsers::class)->callTableAction('toggleActive', $other);
        $this->assertFalse($other->refresh()->is_active);

        Livewire::test(ListAdminUsers::class)->callTableAction('toggleActive', $other);
        $this->assertTrue($other->refresh()->is_active);
    }

    public function test_change_password(): void
    {
        $this->actingAsSuperAdmin();
        $other = $this->admin('LECTURA');

        Livewire::test(ListAdminUsers::class)
            ->callTableAction('changePassword', $other, data: ['password' => 'nuevaClave2026', 'password_confirmation' => 'nuevaClave2026'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('nuevaClave2026', $other->refresh()->password));

        Livewire::test(ListAdminUsers::class)
            ->callTableAction('changePassword', $other, data: ['password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertHasTableActionErrors(['password']);
    }
}
